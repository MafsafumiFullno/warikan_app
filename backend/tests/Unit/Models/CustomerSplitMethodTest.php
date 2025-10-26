<?php

namespace Tests\Unit\Models;

use App\Models\CustomerSplitMethod;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class CustomerSplitMethodTest extends TestCase
{
    public function test_primary_key_name_is_split_method_id(): void
    {
        $model = new CustomerSplitMethod();
        $this->assertSame('split_method_id', $model->getKeyName());
    }

    public function test_mass_assignment_and_casts(): void
    {
        $model = new CustomerSplitMethod([
            'description' => '均等割',
            'template_type' => 'equal',
            'customer_id' => 123,
            'del_flg' => 1, 
            'id' => 999,
            'split_method_id' => 10,
        ]);

        $this->assertSame('均等割', $model->description);
        $this->assertSame('equal', $model->template_type);
        $this->assertSame(123, $model->customer_id);
        $this->assertTrue($model->del_flg);

        // 非フィルタブル属性がマスアサインされなかったことを確認
        $this->assertNull($model->getAttribute('id'));
        $this->assertNull($model->getAttribute('split_method_id'));
    }

    public function test_customer_relation_is_belongs_to(): void
    {
        $model = new CustomerSplitMethod();
        $relation = $model->customer();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_table_name_is_conventional(): void
    {
        $model = new CustomerSplitMethod();
        $this->assertSame('customer_split_methods', $model->getTable());
    }

    public function test_timestamps_default_true(): void
    {
        $model = new CustomerSplitMethod();
        $this->assertTrue($model->usesTimestamps());
    }

    public function test_primary_key_type_and_incrementing(): void
    {
        $model = new CustomerSplitMethod();
        $this->assertSame('int', $model->getKeyType());
        $this->assertTrue($model->getIncrementing());
    }

    public function test_customer_relation_keys_and_related_model(): void
    {
        $model = new CustomerSplitMethod();
        $relation = $model->customer();

        $this->assertSame('customer_id', $relation->getForeignKeyName());
        $this->assertSame('customer_id', $relation->getOwnerKeyName());
        $this->assertSame(\App\Models\Customer::class, get_class($relation->getRelated()));
    }

    public function test_casts_boolean_false_on_zero_string(): void
    {
        $model = new CustomerSplitMethod(['del_flg' => '0']);
        $this->assertFalse($model->del_flg);
    }
}


