import { expect, test } from '@playwright/test';

const member = {
  id: 2,
  project_member_id: 12,
  customer_id: 2,
  role: 'member',
  role_name: 'メンバー',
  split_weight: 1,
  memo: '',
  name: '削除対象メンバー',
  email: 'member@example.com',
  is_guest: false,
  joined_at: '2026-09-01T00:00:00.000Z',
  total_expense: 0,
};

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('auth_token', 'e2e-token'));

  await page.route('**/api/auth/me', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ customer: { customer_id: 1, is_guest: false, nick_name: 'オーナー' } }),
  }));
  await page.route('**/api/projects/1', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({
      project: {
        project_id: 1,
        project_name: 'E2Eプロジェクト',
        description: '',
        project_status: 'active',
        created_at: '2026-09-01T00:00:00.000Z',
        updated_at: '2026-09-01T00:00:00.000Z',
      },
      isOwner: true,
      isMember: true,
    }),
  }));
  await page.route('**/api/projects/1/accountings', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ accountings: [] }),
  }));
  await page.route('**/api/projects/1/members', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ members: [member] }),
  }));
});

test('メンバー削除をキャンセルできる', async ({ page }) => {
  let deleteRequested = false;
  await page.route('**/api/projects/1/members/12', route => {
    deleteRequested = route.request().method() === 'DELETE';
    return route.fulfill({ status: 204 });
  });

  await page.goto('/projects/1');
  await page.getByRole('button', { name: '削除' }).click();

  const dialog = page.getByRole('dialog', { name: 'メンバーを削除' });
  await expect(dialog).toContainText('削除対象メンバーをメンバーから削除しますか？');
  await dialog.getByRole('button', { name: 'キャンセル' }).click();

  await expect(dialog).toBeHidden();
  expect(deleteRequested).toBe(false);
  await expect(page.getByRole('heading', { name: '削除対象メンバー' })).toBeVisible();
});

test('メンバー削除失敗を画面内に表示する', async ({ page }) => {
  await page.route('**/api/projects/1/members/12', route => route.fulfill({
    status: 500,
    contentType: 'application/json',
    body: JSON.stringify({ message: 'メンバーを削除できませんでした' }),
  }));

  await page.goto('/projects/1');
  await page.getByRole('button', { name: '削除' }).click();
  await page.getByRole('dialog', { name: 'メンバーを削除' }).getByRole('button', { name: '削除する' }).click();

  await expect(page.getByRole('alert').filter({ hasText: 'メンバーを削除できませんでした' })).toBeVisible();
  await expect(page.getByRole('heading', { name: '削除対象メンバー' })).toBeVisible();
});
