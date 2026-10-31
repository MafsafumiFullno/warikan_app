<?php

namespace Tests\Feature;

use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Models\Customer;
use App\Models\Project;
use App\Services\Project\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiErrorContractTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): Customer
    {
        $customer = Customer::create(['is_guest' => false, 'del_flg' => false]);
        Sanctum::actingAs($customer);

        return $customer;
    }

    private function createProject(Customer $owner): Project
    {
        return Project::create([
            'customer_id' => $owner->customer_id,
            'project_name' => 'エラー契約テスト',
            'project_status' => 'active',
            'del_flg' => false,
        ]);
    }

    public static function typedErrors(): array
    {
        return [
            'not found' => [NotFoundException::class, 404, 'NOT_FOUND'],
            'forbidden' => [ForbiddenException::class, 403, 'FORBIDDEN'],
            'conflict' => [ConflictException::class, 409, 'CONFLICT'],
        ];
    }

    #[DataProvider('typedErrors')]
    public function test_status_depends_on_exception_type_not_message(string $exceptionClass, int $status, string $code): void
    {
        $this->signIn();
        $this->mock(ProjectService::class, function ($mock) use ($exceptionClass) {
            $mock->shouldReceive('getProjectWithAccessCheck')->once()
                ->andThrow(new $exceptionClass('The displayed wording can change.'));
        });

        $this->getJson('/api/projects/123')->assertStatus($status)->assertExactJson([
            'message' => 'The displayed wording can change.',
            'error' => $code,
            'errors' => [],
        ])->assertContent(json_encode([
            'message' => 'The displayed wording can change.',
            'error' => $code,
            'errors' => (object) [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function unexpectedErrors(): array
    {
        return [
            'production exception' => [false, \RuntimeException::class],
            'debug exception' => [true, \RuntimeException::class],
            'production type error' => [false, \TypeError::class],
        ];
    }

    #[DataProvider('unexpectedErrors')]
    public function test_unexpected_errors_never_use_message_matching_or_leak_details(bool $debug, string $exceptionClass): void
    {
        config(['app.debug' => $debug]);
        $this->signIn();
        $this->mock(ProjectService::class, function ($mock) use ($exceptionClass) {
            $mock->shouldReceive('getProjectWithAccessCheck')->once()
                ->andThrow(new $exceptionClass('secret SQL: 見つかりません アクセス権限がありません'));
        });

        $this->getJson('/api/projects/123')->assertStatus(500)->assertExactJson([
            'message' => 'サーバーエラーが発生しました',
            'error' => 'INTERNAL_SERVER_ERROR',
            'errors' => [],
        ]);
    }

    public function test_missing_project_and_share_link_return_the_same_contract(): void
    {
        $this->signIn();
        $this->getJson('/api/projects/999999')->assertNotFound()->assertExactJson([
            'message' => 'プロジェクトが見つかりません',
            'error' => 'NOT_FOUND',
            'errors' => [],
        ]);

        $this->getJson('/api/share/invalid-token')->assertNotFound()->assertExactJson([
            'message' => '共有リンクが見つかりません',
            'error' => 'NOT_FOUND',
            'errors' => [],
        ]);
    }

    public function test_project_access_and_owner_checks_return_forbidden(): void
    {
        $owner = Customer::create(['is_guest' => false, 'del_flg' => false]);
        $project = $this->createProject($owner);
        $this->signIn();

        $this->getJson("/api/projects/{$project->project_id}")->assertForbidden()
            ->assertJsonPath('error', 'FORBIDDEN');
        $this->putJson("/api/projects/{$project->project_id}", ['project_name' => '変更'])
            ->assertForbidden()->assertJsonPath('error', 'FORBIDDEN');
    }

    public function test_duplicate_member_returns_conflict(): void
    {
        $owner = $this->signIn();
        $project = $this->createProject($owner);
        $path = "/api/projects/{$project->project_id}/members";
        $this->postJson($path, ['name' => '同じメンバー'])->assertCreated();

        $this->postJson($path, ['name' => '同じメンバー'])->assertConflict()->assertExactJson([
            'message' => 'このメンバーは既に追加されています',
            'error' => 'CONFLICT',
            'errors' => [],
        ]);
    }

    public function test_invalid_split_method_has_field_errors(): void
    {
        $owner = $this->signIn();
        $project = $this->createProject($owner);

        $this->putJson("/api/projects/{$project->project_id}", ['split_method_id' => 999999])
            ->assertUnprocessable()->assertJsonPath('message', 'バリデーションエラー')
            ->assertJsonPath('error', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['split_method_id']);
    }

    public function test_missing_member_and_accounting_return_not_found(): void
    {
        $owner = $this->signIn();
        $project = $this->createProject($owner);

        $this->deleteJson("/api/projects/{$project->project_id}/members/999999")
            ->assertNotFound()->assertJsonPath('error', 'NOT_FOUND');
        $this->deleteJson("/api/projects/{$project->project_id}/accountings/999999")
            ->assertNotFound()->assertJsonPath('error', 'NOT_FOUND');
    }

    public function test_validation_contract_is_shared_by_project_and_auth_endpoints(): void
    {
        $this->postJson('/api/auth/register', [])->assertUnprocessable()
            ->assertJsonPath('error', 'VALIDATION_ERROR')->assertJsonStructure(['message', 'error', 'errors']);
        $this->signIn();
        $this->postJson('/api/projects', [])->assertUnprocessable()
            ->assertJsonPath('error', 'VALIDATION_ERROR')->assertJsonValidationErrors(['project_name']);
    }

    public function test_auth_bad_requests_keep_their_status_and_use_the_contract(): void
    {
        $this->signIn();

        $this->postJson('/api/auth/upgrade-to-member', [])->assertBadRequest()->assertExactJson([
            'message' => '既に会員登録済みです',
            'error' => 'BAD_REQUEST',
            'errors' => [],
        ]);
        $this->putJson('/api/auth/profile', [])->assertBadRequest()->assertJsonPath('error', 'BAD_REQUEST');
    }

    public function test_missing_internal_role_is_not_exposed_as_resource_not_found(): void
    {
        $owner = $this->signIn();
        $project = $this->createProject($owner);
        \App\Models\ProjectRole::query()->delete();

        $this->putJson("/api/projects/{$project->project_id}", ['project_name' => '変更'])
            ->assertStatus(500)->assertExactJson([
                'message' => 'サーバーエラーが発生しました',
                'error' => 'INTERNAL_SERVER_ERROR',
                'errors' => [],
            ]);
    }

    public function test_authentication_and_routing_errors_are_json_without_accept_header(): void
    {
        $this->get('/api/projects')->assertUnauthorized()->assertExactJson([
            'message' => '認証が必要です',
            'error' => 'UNAUTHENTICATED',
            'errors' => [],
        ]);
        $this->get('/api/nonexistent-route')->assertNotFound()->assertJsonPath('error', 'NOT_FOUND');
    }

    public function test_http_error_headers_are_preserved(): void
    {
        Route::get('/api/contract-rate-limit', function () {
            abort(429, 'internal limiter detail', ['Retry-After' => '60']);
        });

        $this->getJson('/api/contract-rate-limit')->assertStatus(429)
            ->assertHeader('Retry-After', '60')->assertExactJson([
                'message' => 'Too Many Requests',
                'error' => 'TOO_MANY_REQUESTS',
                'errors' => [],
            ]);
    }
}
