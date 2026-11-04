import { expect, test, type APIRequestContext } from '@playwright/test';

async function csrfHeaders(request: APIRequestContext) {
  const res = await request.get('/');
  expect(res.ok()).toBeTruthy();
  const cookies = await request.storageState();
  const xsrf = cookies.cookies.find((c) => c.name === 'XSRF-TOKEN')?.value;
  return { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(xsrf ?? '') };
}

test('homepage renders the DeploySift workspace', async ({ page }) => {
  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'Every clue. One diagnosis.' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Start investigation' })).toBeVisible();
});

test('auth pages render and register through the UI', async ({ page }) => {
  await page.goto('/login');
  await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
  await page.getByLabel('Email').fill(`ui-${Date.now()}@example.test`);
  await expect(page.getByRole('link', { name: 'Register' })).toBeVisible();
  await page.goto('/register');
  await expect(page.getByRole('heading', { name: 'Create account' })).toBeVisible();
  await page.getByLabel('Name').fill('E2E UI User');
  const email = `ui-reg-${Date.now()}@example.test`;
  await page.getByLabel('Email').fill(email);
  await page.getByLabel(/Password \(min/).fill('password123');
  await page.getByLabel('Confirm password').fill('password123');
  await page.getByRole('button', { name: 'Register', exact: true }).click();
  await page.waitForURL(/\/$/);
  await expect(page.locator('#ws option').first()).toBeAttached();
});

test('complete demo workflow: register, demo, findings, report, export, cleanup', async ({ request }) => {
  const stamp = Date.now();
  let headers = await csrfHeaders(request);

  const register = await request.post('/auth/register', {
    headers,
    data: { name: 'E2E User', email: `e2e-${stamp}@example.test`, password: 'password123', password_confirmation: 'password123' },
  });
  expect(register.status()).toBe(201);
  headers = await csrfHeaders(request);

  const workspaces = await request.get('/api/workspaces', { headers });
  expect(workspaces.ok()).toBeTruthy();
  const [workspace] = await workspaces.json();
  expect(workspace.role).toBe('owner');

  const demo = await request.post('/api/demo/php', { headers, data: { workspace_id: workspace.id } });
  expect(demo.status()).toBe(201);
  const investigation = await demo.json();
  const identifiers = investigation.findings.map((f: { identifier: string }) => f.identifier);
  expect(identifiers).toContain('PHP_VERSION_MISMATCH');

  const investigationId = investigation.id;
  const report = await request.get(`/api/investigations/${investigationId}/report`, { headers });
  expect(report.ok()).toBeTruthy();
  const body = await report.json();
  expect(body.findings[0].recommended_actions.length).toBeGreaterThan(0);
  expect(body.verification_checklist.length).toBeGreaterThan(0);
  const evidence = await request.get(`/api/investigations/${investigationId}/evidence`, { headers });
  const knownIds = (await evidence.json()).map((e: { id: number }) => e.id);
  for (const finding of body.findings) {
    for (const id of finding.evidence_ids) expect(knownIds).toContain(id);
  }

  const exported = await request.get(`/api/investigations/${investigationId}/report/export`, { headers });
  expect(exported.ok()).toBeTruthy();
  expect(exported.headers()['content-disposition']).toContain('attachment');

  const ai = await request.get(`/api/investigations/${investigationId}/ai`, { headers });
  expect((await ai.json()).status).toBe('configured');
  const analysis = await request.post(`/api/investigations/${investigationId}/ai`, { headers });
  const analysisBody = await analysis.json();
  expect(analysisBody.status).toBe('completed');
  expect(analysisBody.insights.length).toBeGreaterThan(0);
  for (const insight of analysisBody.insights) {
    for (const id of insight.evidence_ids) expect(knownIds).toContain(id);
  }

  const deleted = await request.delete(`/api/investigations/${investigationId}`, { headers });
  expect(deleted.status()).toBe(204);
  expect((await request.get(`/api/investigations/${investigationId}`, { headers })).status()).toBe(404);
});

test('browser upload flow with validation and progress', async ({ browser, request }) => {
  const stamp = Date.now();
  let headers = await csrfHeaders(request);
  const register = await request.post('/auth/register', {
    headers,
    data: { name: 'E2E Upload', email: `e2e-up-${stamp}@example.test`, password: 'password123', password_confirmation: 'password123' },
  });
  expect(register.status()).toBe(201);
  headers = await csrfHeaders(request);
  const created = await request.post('/api/investigations', { headers, data: { name: `Upload UI ${stamp}`, workspace_id: (await (await request.get('/api/workspaces', { headers })).json())[0].id } });
  expect(created.status()).toBe(201);
  const invId = (await created.json()).id;

  const ctx = await browser.newContext({ storageState: await request.storageState() });
  const page = await ctx.newPage();
  await page.goto('/');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('#authbox')).toContainText('@example.test');
  await page.waitForFunction(() => (document.querySelector('#ws')?.options.length ?? 0) > 0);
  await page.evaluate((id) => { selected = id; view = 'inv'; load(); }, invId);
  await expect(page.getByRole('heading', { name: 'Add evidence' })).toBeVisible();
  await page.selectOption('#evcat', 'logs');
  await expect(page.locator('#evcat')).toHaveValue('logs');
  const evcatCount = await page.evaluate(() => document.querySelectorAll('#evcat').length);
  expect(evcatCount).toBe(1);

  await page.setInputFiles('#evfile', { name: 'tool.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('MZ') });
  await expect(page.getByText('failed: Unsupported evidence type.')).toBeVisible();
  await page.getByRole('button', { name: 'Retry' }).click();
  await expect(page.getByText('failed: Unsupported evidence type.')).toBeVisible();

  await page.selectOption('#evcat', 'logs');
  await expect(page.locator('#evcat')).toHaveValue('logs');
  await page.setInputFiles('#evfile', { name: 'laravel.log', mimeType: 'text/plain', buffer: Buffer.from('SQLSTATE[HY000] [1045] Access denied for user') });
  await expect.poll(async () => (await (await request.get(`/api/investigations/${invId}/evidence`, { headers })).json()).length, { timeout: 20000 }).toBe(1);
  const files = await (await request.get(`/api/investigations/${invId}/evidence`, { headers })).json();
  expect(files[0].original_name).toBe('laravel.log');
  expect(files[0].category).toBe('logs');
  await ctx.close();
});

test('empty states guide to an investigation', async ({ browser, request }) => {
  let headers = await csrfHeaders(request);
  await request.post('/auth/register', { headers, data: { name: 'E2E Empty', email: `e2e-empty-${Date.now()}@example.test`, password: 'password123', password_confirmation: 'password123' } });
  headers = await csrfHeaders(request);
  const ctx = await browser.newContext({ storageState: await request.storageState() });
  const page = await ctx.newPage();
  await page.goto('/');
  await page.waitForLoadState('networkidle');
  await page.waitForFunction(() => (document.querySelector('#ws')?.options.length ?? 0) > 0);
  for (const nav of ['Reports', 'Evidence', 'AI Analysis']) {
    await page.locator('aside').getByText(nav, { exact: true }).click();
    await expect(page.getByRole('button', { name: 'Browse investigations' })).toBeVisible();
  }
  await page.getByRole('button', { name: 'Browse investigations' }).click();
  await expect(page.getByRole('heading', { name: 'Investigations' })).toBeVisible();
  await ctx.close();
});

test('invalid investigation id shows unavailable state', async ({ browser, request }) => {
  let headers = await csrfHeaders(request);
  await request.post('/auth/register', { headers, data: { name: 'E2E Gone', email: `e2e-gone-${Date.now()}@example.test`, password: 'password123', password_confirmation: 'password123' } });
  const ctx = await browser.newContext({ storageState: await request.storageState() });
  const page = await ctx.newPage();
  await page.goto('/');
  await page.waitForLoadState('networkidle');
  await page.waitForFunction(() => (document.querySelector('#ws')?.options.length ?? 0) > 0);
  await page.evaluate(() => { selected = 424242; view = 'inv'; load(); });
  await expect(page.getByText('Investigation unavailable')).toBeVisible();
  await ctx.close();
});

test('initial boot issues one request per endpoint', async ({ browser, request }) => {
  let headers = await csrfHeaders(request);
  await request.post('/auth/register', { headers, data: { name: 'E2E Boot', email: `e2e-boot-${Date.now()}@example.test`, password: 'password123', password_confirmation: 'password123' } });
  const ctx = await browser.newContext({ storageState: await request.storageState() });
  const page = await ctx.newPage();
  const counts: Record<string, number> = {};
  await page.route('**/api/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    counts[path] = (counts[path] ?? 0) + 1;
    await route.continue();
  });
  await page.goto('/');
  await page.waitForLoadState('networkidle');
  await page.waitForFunction(() => (document.querySelector('#ws')?.options.length ?? 0) > 0);
  await page.waitForTimeout(1500);
  expect(counts['/api/workspaces'] ?? 0).toBeLessThanOrEqual(1);
  expect(counts['/api/me'] ?? 0).toBeLessThanOrEqual(1);
  expect(counts['/api/dashboard'] ?? 0).toBeLessThanOrEqual(1);
  await ctx.close();
});
