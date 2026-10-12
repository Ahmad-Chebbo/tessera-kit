<?php

use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Engine\ReportRegistry;
use App\Modules\Exports\Enums\ExportStatus;
use App\Modules\Exports\Jobs\GenerateReportExport;
use App\Modules\Exports\Models\ExportFile;
use App\Modules\Exports\Services\ExportService;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Orders\Models\Order;
use App\Modules\Pos\Models\PosShift;
use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;

/**
 * @return list<list<mixed>>
 */
function readXlsx(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();

    return $rows;
}

function exportPath(ExportFile $export): string
{
    return Storage::disk(config('exports.disk'))->path($export->path);
}

function seedOrders(object $test, int $count = 2): void
{
    for ($i = 1; $i <= $count; $i++) {
        $order = Order::create([
            'tenant_id' => $test->tenant->id, 'branch_id' => $test->branch->id, 'order_number' => "ORD-$i",
            'subtotal' => 100 * $i, 'discount_amount' => 10 * $i, 'total' => 90 * $i, 'status' => 'completed',
            'cashier_id' => $test->user->id, 'customer_id' => $test->customer->id, 'payment_type' => 'cash',
        ]);
        $order->items()->create(['product_id' => $test->product->id, 'quantity' => 2, 'unit_price' => 50 * $i, 'cost_price' => 30, 'total' => 100 * $i]);
    }
}

beforeEach(function () {
    Storage::fake('local');

    foreach (['export reports', 'view reports', 'view orders', 'view all orders', 'view attendance', 'view inventory', 'view shift reports', 'open pos shift', 'view expenses'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->tenant = Tenant::factory()->create(['name' => 'Beirut Mart', 'timezone' => 'UTC']);
    $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Main']);
    $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'type' => 'branch', 'name' => 'Main shelf']);
    $this->user = User::factory()->create(['name' => 'Layla', 'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id])
        ->givePermissionTo(['export reports', 'view reports', 'view orders', 'view all orders', 'view attendance', 'view inventory', 'view shift reports', 'open pos shift', 'view expenses']);
    $this->category = Category::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Drinks']);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $this->category->id, 'name' => 'Cola', 'sku' => 'COLA-1', 'cost_price' => 30, 'base_price' => 50, 'is_active' => true]);
    $this->customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Rana']);

    seedOrders($this);
    StockMovement::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'location_id' => $this->location->id, 'quantity' => 40]);
    AttendanceRecord::factory()->create(['user_id' => $this->user->id, 'work_date' => now()->toDateString(), 'check_in_at' => now()->subHours(8), 'check_out_at' => now(), 'worked_minutes' => 480]);
    $this->shift = PosShift::factory()->closed()->create(['user_id' => $this->user->id, 'summary' => ['orders' => 2, 'items' => 4, 'gross_sales' => 300, 'discounts' => 30, 'cash_sales' => 270, 'credit_sales' => 0, 'voided_orders' => 0, 'voided_total' => 0]]);
});

function requestExport(object $test, string $report, string $format = 'xlsx', string $locale = 'en', array $filters = [], ?User $as = null)
{
    return test()->actingAs($as ?? $test->user)->postJson(route('exports.store'), compact('report', 'format', 'locale', 'filters'));
}

describe('every report', function () {
    $filtersFor = fn (string $key, object $test): array => match ($key) {
        'pos-shift-closing' => ['shift_id' => $test->shift->id],
        default => [],
    };

    it('exports to Excel and PDF in English and Arabic', function (string $report, string $format, string $locale) use ($filtersFor) {
        $response = requestExport($this, $report, $format, $locale, $filtersFor($report, $this))->assertCreated()->assertJsonPath('status', 'ready');

        $export = ExportFile::findOrFail($response->json('id'));
        $path = exportPath($export);

        expect(is_file($path))->toBeTrue()->and($export->size)->toBeGreaterThan(500);

        if ($format === 'pdf') {
            expect(file_get_contents($path, false, null, 0, 5))->toBe('%PDF-');
        } else {
            $rows = readXlsx($path);
            expect($rows[0][0])->toBe(__("exports::exports.reports.{$report}", [], $locale))->and($rows[1][0])->toBe('Beirut Mart');
        }

        $this->actingAs($this->user)->get($response->json('download_url'))->assertOk()->assertHeader('content-type', $export->format->mimeType());
    })->with(fn () => collect(['sales', 'items', 'profit', 'orders', 'attendance', 'attendance-history', 'stock-levels', 'stock-movements', 'pos-shifts', 'pos-shift-closing', 'expenses', 'expenses-report'])->crossJoin(['xlsx', 'pdf'], ['en', 'ar'])->all());
});

describe('excel content', function () {
    it('writes numbers as numbers, with the heading, summary and a header row', function () {
        $export = ExportFile::findOrFail(requestExport($this, 'sales', 'xlsx', 'en', ['date_from' => now()->subDay()->toDateString(), 'date_to' => now()->toDateString()])->json('id'));
        $rows = readXlsx(exportPath($export));

        expect($rows[0][0])->toBe('Sales report')
            ->and($rows[2][0])->toStartWith('Generated ')
            ->and(collect($rows)->contains(fn ($row) => ($row[0] ?? null) === 'Total sales' && $row[1] === 270))->toBeTrue();

        $header = collect($rows)->first(fn ($row) => ($row[0] ?? null) === 'Date');
        expect($header)->toBe(['Date', 'Orders', 'Gross sales (USD)', 'Discounts (USD)', 'Net sales (USD)', 'Average order (USD)']);

        $data = collect($rows)->first(fn ($row) => ($row[1] ?? null) === 2 && is_numeric($row[2] ?? null));
        expect($data[2])->toEqual(300)->and($data[4])->toEqual(270)->and($data[5])->toEqual(135);
    });

    it('uses Arabic headings and a right-to-left sheet for Arabic', function () {
        $export = ExportFile::findOrFail(requestExport($this, 'items', 'xlsx', 'ar')->json('id'));
        $rows = readXlsx(exportPath($export));
        $header = collect($rows)->first(fn ($row) => ($row[0] ?? null) === 'المنتج');

        expect($rows[0][0])->toBe('تقرير مبيعات الأصناف')
            ->and($header)->toContain('الإيرادات (USD)', 'الفئة')
            ->and(collect($rows)->contains(fn ($row) => in_array('Cola', $row, true)))->toBeTrue();

        $zip = new ZipArchive;
        $zip->open(exportPath($export));
        expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('rightToLeft="true"');
    });

    it('keeps text that looks like a formula as text', function () {
        $this->product->update(['name' => '=SUM(1+1)']);

        $export = ExportFile::findOrFail(requestExport($this, 'items')->json('id'));
        $zip = new ZipArchive;
        $zip->open(exportPath($export));

        expect($zip->getFromName('xl/worksheets/sheet1.xml'))->not->toContain('<f>');
        expect(collect(readXlsx(exportPath($export)))->contains(fn ($row) => in_array('=SUM(1+1)', $row, true)))->toBeTrue();
    });

    it('computes profit and margin', function () {
        $export = ExportFile::findOrFail(requestExport($this, 'profit')->json('id'));
        $rows = collect(readXlsx(exportPath($export)));
        $row = $rows->first(fn ($row) => ($row[0] ?? null) === 'Cola');

        expect($row[4])->toEqual(300)->and($row[5])->toEqual(120)->and($row[6])->toEqual(180)->and($row[7])->toEqual(60);
    });

    it('reports stock as the sum of movements and values it at cost', function () {
        $export = ExportFile::findOrFail(requestExport($this, 'stock-levels')->json('id'));
        $row = collect(readXlsx(exportPath($export)))->first(fn ($row) => ($row[0] ?? null) === 'Cola');

        expect($row[3])->toEqual(40)->and($row[5])->toEqual(1200);
    });

    it('limits stock to one location', function () {
        $other = Location::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'type' => 'warehouse']);
        StockMovement::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'location_id' => $other->id, 'quantity' => 10]);

        $all = collect(readXlsx(exportPath(ExportFile::findOrFail(requestExport($this, 'stock-levels')->json('id')))))->first(fn ($row) => ($row[0] ?? null) === 'Cola');
        $one = collect(readXlsx(exportPath(ExportFile::findOrFail(requestExport($this, 'stock-levels', 'xlsx', 'en', ['location_id' => $other->id])->json('id')))))->first(fn ($row) => ($row[0] ?? null) === 'Cola');

        expect($all[3])->toEqual(50)->and($one[3])->toEqual(10);
    });
});

describe('filters', function () {
    it('applies the order filters and describes them in the heading', function () {
        Order::where('order_number', 'ORD-2')->update(['status' => 'voided']);

        $export = ExportFile::findOrFail(requestExport($this, 'orders', 'xlsx', 'en', ['status' => 'voided'])->json('id'));
        $rows = readXlsx(exportPath($export));
        $orders = collect($rows)->filter(fn ($row) => str_starts_with((string) ($row[0] ?? ''), 'ORD-'));

        expect($orders)->toHaveCount(1)->and($orders->first()[0])->toBe('ORD-2')->and(collect($rows)->flatten()->contains('Status: Voided'))->toBeTrue();
    });

    it('limits orders to the branch of users who cannot see every branch', function () {
        $other = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        Order::where('order_number', 'ORD-2')->update(['branch_id' => $other->id]);
        $this->user->revokePermissionTo('view all orders');

        $orders = collect(readXlsx(exportPath(ExportFile::findOrFail(requestExport($this, 'orders')->json('id')))))->filter(fn ($row) => str_starts_with((string) ($row[0] ?? ''), 'ORD-'));

        expect($orders)->toHaveCount(1)->and($orders->first()[0])->toBe('ORD-1');
    });

    it('never exports another tenant\'s data', function () {
        $other = Tenant::factory()->create();
        $stranger = User::factory()->create(['tenant_id' => $other->id])->givePermissionTo(['export reports', 'view orders', 'view all orders']);

        $rows = readXlsx(exportPath(ExportFile::findOrFail(requestExport($this, 'orders', as: $stranger)->json('id'))));

        expect(collect($rows)->filter(fn ($row) => str_starts_with((string) ($row[0] ?? ''), 'ORD-')))->toHaveCount(0);
    });

    it('rejects filters pointing at other tenants and malformed dates', function () {
        $foreign = Category::factory()->create();

        requestExport($this, 'items', filters: ['category_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors('filters.category_id');
        requestExport($this, 'sales', filters: ['date_from' => 'yesterday'])->assertUnprocessable()->assertJsonValidationErrors('filters.date_from');
    });

    it('needs a shift for the closing report and only the cashier or a manager may export it', function () {
        requestExport($this, 'pos-shift-closing')->assertUnprocessable();

        $cashier = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id])->givePermissionTo(['export reports', 'open pos shift']);
        requestExport($this, 'pos-shift-closing', filters: ['shift_id' => $this->shift->id], as: $cashier)->assertUnprocessable();

        $own = PosShift::factory()->closed()->create(['user_id' => $cashier->id, 'summary' => []]);
        requestExport($this, 'pos-shift-closing', filters: ['shift_id' => $own->id], as: $cashier)->assertCreated();
    });
});

describe('large exports', function () {
    it('queues exports above the synchronous limit and generates them in the worker', function () {
        config(['exports.sync_limit' => 1]);
        Queue::fake();

        $response = requestExport($this, 'orders')->assertCreated()->assertJsonPath('status', 'queued')->assertJsonPath('download_url', null);

        Queue::assertPushedOn('exports', GenerateReportExport::class);

        $export = ExportFile::findOrFail($response->json('id'));
        (new GenerateReportExport($export->id))->handle(app(ExportService::class));

        expect($export->fresh()->status)->toBe(ExportStatus::Ready)->and($export->fresh()->rows)->toBe(2);
    });

    it('keeps memory flat while streaming many rows', function () {
        $rows = 3000;
        $now = now()->toDateTimeString();
        $insert = [];

        for ($i = 0; $i < $rows; $i++) {
            $insert[] = ['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'order_number' => "BULK-$i", 'subtotal' => 10, 'total' => 10, 'status' => 'completed', 'cashier_id' => $this->user->id, 'payment_type' => 'cash', 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_chunk($insert, 500) as $chunk) {
            Order::insert($chunk);
        }

        $export = ExportFile::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'report' => 'orders', 'format' => 'xlsx', 'locale' => 'en', 'filters' => [], 'status' => 'queued']);

        $before = memory_get_peak_usage(true);
        app(ExportService::class)->generate($export);
        $growth = memory_get_peak_usage(true) - $before;

        expect($export->fresh()->rows)->toBe($rows + 2)
            ->and($growth)->toBeLessThan(40 * 1024 * 1024);
    });

    it('sends PDFs to the background sooner than Excel', function () {
        config(['exports.sync_limit' => 100, 'exports.pdf_sync_limit' => 1]);

        requestExport($this, 'orders', 'xlsx')->assertJsonPath('status', 'ready');

        Queue::fake();
        requestExport($this, 'orders', 'pdf')->assertJsonPath('status', 'queued');
    });

    it('refuses a PDF that is too big and points to Excel', function () {
        config(['exports.pdf_max_rows' => 1]);

        requestExport($this, 'orders', 'pdf')->assertUnprocessable()->assertJsonValidationErrors('format');
        requestExport($this, 'orders', 'xlsx')->assertCreated();
    });

    it('marks a failed export without leaking the error', function () {
        $export = ExportFile::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'report' => 'orders', 'format' => 'xlsx', 'locale' => 'en', 'filters' => [], 'status' => 'queued']);

        app(ExportService::class)->fail($export, new RuntimeException('secret path /var/www'));

        expect($export->fresh()->status)->toBe(ExportStatus::Failed)->and($export->fresh()->error)->not->toContain('secret');
    });

    it('deletes expired files and abandoned jobs', function () {
        $ready = ExportFile::findOrFail(requestExport($this, 'orders')->json('id'));
        $path = $ready->path;
        $ready->update(['expires_at' => now()->subMinute()]);
        $stuck = ExportFile::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'report' => 'orders', 'format' => 'xlsx', 'locale' => 'en', 'status' => 'queued']);
        $stuck->forceFill(['created_at' => now()->subDays(2)])->save();
        $fresh = ExportFile::findOrFail(requestExport($this, 'sales')->json('id'));

        expect(app(ExportService::class)->pruneExpired())->toBe(2);

        expect(ExportFile::find($ready->id))->toBeNull()->and(Storage::disk('local')->exists($path))->toBeFalse()
            ->and(ExportFile::find($stuck->id))->toBeNull()->and(ExportFile::find($fresh->id))->not->toBeNull();
    });
});

describe('access', function () {
    it('needs the export permission and the exports module', function () {
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id])->givePermissionTo(['view reports']);
        requestExport($this, 'sales', as: $viewer)->assertForbidden();

        $plan = Plan::factory()->withModules([Module::Pos, Module::Reports])->create();
        $this->tenant->subscriptions()->delete();
        Subscription::create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay()]);

        requestExport($this, 'sales')->assertForbidden();
        $this->actingAs($this->user)->get(route('exports.index'))->assertForbidden();
    });

    it('needs the permission of the report itself', function () {
        $this->user->revokePermissionTo('view orders');

        requestExport($this, 'orders')->assertUnprocessable()->assertJsonValidationErrors('report');
    });

    it('rejects unknown reports, formats and languages', function () {
        requestExport($this, 'payroll')->assertUnprocessable();
        requestExport($this, 'sales', 'docx')->assertUnprocessable();
        requestExport($this, 'sales', 'xlsx', 'fr')->assertUnprocessable();
    });

    it('only lets people download their own files', function () {
        $export = ExportFile::findOrFail(requestExport($this, 'sales')->json('id'));
        $colleague = User::factory()->create(['tenant_id' => $this->tenant->id])->givePermissionTo('export reports');
        $stranger = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id])->givePermissionTo('export reports');

        $this->actingAs($colleague)->get(route('exports.download', $export))->assertForbidden();
        $this->actingAs($stranger)->get(route('exports.download', $export))->assertForbidden();
        $this->actingAs($colleague)->delete(route('exports.destroy', $export))->assertForbidden();
        $this->actingAs($this->user)->get(route('exports.download', $export))->assertOk();
    });

    it('does not serve expired or missing files', function () {
        $export = ExportFile::findOrFail(requestExport($this, 'sales')->json('id'));
        $export->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($this->user)->get(route('exports.download', $export))->assertNotFound();
    });

    it('lists only my exports and deletes the file with the record', function () {
        $mine = ExportFile::findOrFail(requestExport($this, 'sales')->json('id'));
        $other = User::factory()->create(['tenant_id' => $this->tenant->id])->givePermissionTo('export reports');
        requestExport($this, 'sales', as: $other);

        $this->actingAs($this->user)->get(route('exports.index'))
            ->assertInertia(fn ($page) => $page->component('Exports/Index')->has('exports', 1)->where('exports.0.id', $mine->id));

        $this->actingAs($this->user)->delete(route('exports.destroy', $mine))->assertRedirect();
        expect(ExportFile::find($mine->id))->toBeNull()->and(Storage::disk('local')->exists($mine->path))->toBeFalse();
    });
});

it('lists the reports the modules offer', function () {
    expect(app(ReportRegistry::class)->keys())->toEqualCanonicalizing(['sales', 'items', 'profit', 'orders', 'attendance', 'attendance-history', 'stock-levels', 'stock-movements', 'pos-shifts', 'pos-shift-closing', 'expenses', 'expenses-report']);
});
