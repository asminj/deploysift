<?php
namespace App\Services;

use App\Models\Investigation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MuseAdapter
{
    private const CONFIDENCE_LEVELS = ['verified observation', 'strongly supported inference', 'possible explanation', 'missing information', 'contradictory evidence'];
    private const MAX_IMAGES = 3;
    private const MAX_IMAGE_BYTES = 2097152;

    public function configured(): bool
    {
        return (bool) (config('services.muse.key') && config('services.muse.endpoint'));
    }

    public function status(): array
    {
        $key = config('services.muse.key');
        $endpoint = config('services.muse.endpoint');
        if ($key && $endpoint) {
            $note = 'Muse credentials are configured. Every AI insight is validated against stored evidence IDs before use; ungrounded output is rejected.';
        } elseif (! $key && ! $endpoint) {
            $note = 'No Muse credentials are configured (MUSE_API_KEY and MUSE_API_ENDPOINT are both empty). Deterministic findings are the source of truth; no AI result is fabricated and no AI call is attempted.';
        } elseif (! $key) {
            $note = 'Muse endpoint is configured but MUSE_API_KEY is missing. Set it in the server environment, then re-run analysis.';
        } else {
            $note = 'Muse API key is present but MUSE_API_ENDPOINT is missing. Set it in the server environment, then re-run analysis.';
        }

        return [
            'provider' => 'meta-muse',
            'model' => config('services.muse.model'),
            'status' => $this->configured() ? 'configured' : 'not_configured',
            'note' => $note,
        ];
    }

    public function analyze(Investigation $investigation): array
    {
        if (! $this->configured()) {
            $run = $investigation->aiRuns()->create([
                'provider' => 'meta-muse',
                'model' => config('services.muse.model'),
                'state' => 'skipped',
                'error' => 'Muse credentials are not configured; no AI call was attempted.',
            ]);

            return ['status' => 'not_configured', 'run_id' => $run->id];
        }

        $package = $this->buildPackage($investigation->load('evidence', 'findings'));
        try {
            $response = Http::withToken((string) config('services.muse.key'))
                ->timeout(60)->retry(2, 1000, throw: false)
                ->post(rtrim((string) config('services.muse.endpoint'), '/').'/v1/responses', [
                    'model' => config('services.muse.model'),
                    'instructions' => $this->systemPrompt(),
                    'input' => $this->buildInput($package),
                    'reasoning' => ['effort' => 'low'],
                    'max_output_tokens' => 4000,
                    'store' => false,
                ]);
        } catch (\Throwable $e) {
            $run = $investigation->aiRuns()->create([
                'provider' => 'meta-muse',
                'model' => config('services.muse.model'),
                'state' => 'failed',
                'input' => $package,
                'error' => 'Muse request failed: '.$e->getMessage(),
            ]);

            return ['status' => 'error', 'run_id' => $run->id, 'error' => 'Muse request failed; deterministic findings stand.'];
        }

        if (! $response->successful()) {
            $run = $investigation->aiRuns()->create([
                'provider' => 'meta-muse',
                'model' => config('services.muse.model'),
                'state' => 'failed',
                'input' => $package,
                'error' => 'Muse API returned HTTP '.$response->status().': '.substr((string) $response->body(), 0, 500),
            ]);

            return ['status' => 'error', 'run_id' => $run->id, 'error' => 'Muse request failed; deterministic findings stand.'];
        }

        $payload = $response->json();
        $insights = $this->extractInsights($this->responseText($payload));
        if (! $this->validOutput($insights, $investigation)) {
            $run = $investigation->aiRuns()->create([
                'provider' => 'meta-muse',
                'model' => config('services.muse.model'),
                'state' => 'invalid',
                'input' => $package,
                'output' => ['raw' => substr((string) json_encode($payload), 0, 2000)],
                'error' => 'Muse response failed evidence-reference validation and was rejected.',
            ]);

            return ['status' => 'invalid', 'run_id' => $run->id, 'error' => 'Muse response failed validation and was rejected; deterministic findings stand.'];
        }

        $run = $investigation->aiRuns()->create([
            'provider' => 'meta-muse',
            'model' => $payload['model'] ?? config('services.muse.model'),
            'state' => 'completed',
            'input' => $package,
            'output' => ['insights' => $insights, 'usage' => $payload['usage'] ?? null],
        ]);

        return ['status' => 'completed', 'run_id' => $run->id, 'insights' => $insights];
    }

    private function systemPrompt(): string
    {
        $levels = implode(', ', self::CONFIDENCE_LEVELS);

        return 'You are a deployment-failure analyst. You receive evidence facts and deterministic findings about a Laravel/PHP deployment. '
            .'Respond with strict JSON only, no prose, no code fences: {"insights": [{"title": string, "explanation": string, '
            .'"confidence": one of ['.$levels.'], "evidence_ids": [integers]}]}. '
            .'Every evidence_id must come from the supplied evidence list; never invent IDs. '
            .'Distinguish verified observation from inference; never claim a fix was tested. '
            .'If evidence is insufficient, return insights with confidence "missing information" and empty evidence_ids, explaining what is missing.';
    }

    private function responseText(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }
        foreach ((array) ($payload['output'] ?? []) as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'output_text' && isset($part['text'])) {
                    return $part['text'];
                }
            }
        }

        return null;
    }

    private function buildInput(array $package): array|string
    {
        $text = 'Evidence package (redacted, secrets removed): '.json_encode($package);
        $images = $this->imageParts($package['investigation']['id']);
        if ($images === []) {
            return $text;
        }

        return [['role' => 'user', 'content' => array_merge([['type' => 'input_text', 'text' => $text.' Interpret attached hosting/browser screenshots only for values visibly present; never claim unseen configuration.']], $images)]];
    }

    private function imageParts(int $investigationId): array
    {
        $parts = [];
        $files = \App\Models\EvidenceFile::where('investigation_id', $investigationId)
            ->whereNotNull('storage_path')->latest()->take(10)->get();
        foreach ($files as $file) {
            if (count($parts) >= self::MAX_IMAGES) {
                break;
            }
            if (! str_starts_with((string) $file->mime_type, 'image/')) {
                continue;
            }
            if (($file->size ?? 0) > self::MAX_IMAGE_BYTES || ! Storage::disk('local')->exists($file->storage_path)) {
                continue;
            }
            $parts[] = ['type' => 'input_image', 'image_url' => 'data:'.$file->mime_type.';base64,'.base64_encode(Storage::disk('local')->get($file->storage_path))];
        }

        return $parts;
    }

    private function buildPackage(Investigation $investigation): array
    {
        return [
            'investigation' => ['id' => $investigation->id, 'framework' => $investigation->framework, 'problem' => $investigation->problem],
            'evidence' => $investigation->evidence->map(fn ($e) => ['id' => $e->id, 'category' => $e->category, 'name' => $e->original_name, 'facts' => $e->extracted])->all(),
            'findings' => $investigation->findings->map(fn ($f) => ['identifier' => $f->identifier, 'title' => $f->title, 'evidence_ids' => $f->evidence_ids])->all(),
        ];
    }

    private function extractInsights(mixed $content): mixed
    {
        if (! is_string($content)) {
            return null;
        }
        $text = trim(preg_replace('/^```(?:json)?|```$/', '', trim($content)));
        $decoded = json_decode($text, true);

        return is_array($decoded) ? ($decoded['insights'] ?? null) : null;
    }

    private function validOutput(mixed $insights, Investigation $investigation): bool
    {
        if (! is_array($insights) || $insights === []) {
            return false;
        }
        $known = $investigation->evidence->pluck('id')->all();
        foreach ($insights as $insight) {
            if (! is_array($insight) || ! isset($insight['title'], $insight['explanation'], $insight['confidence'], $insight['evidence_ids'])) {
                return false;
            }
            if (! in_array($insight['confidence'], self::CONFIDENCE_LEVELS, true)) {
                return false;
            }
            foreach ((array) $insight['evidence_ids'] as $id) {
                if (! in_array($id, $known, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function redact(string $text): string
    {
        return (string) preg_replace('/(password|secret|token|api[_-]?key)\s*[=:]\s*\S+/i', '$1=[REDACTED]', $text);
    }
}
