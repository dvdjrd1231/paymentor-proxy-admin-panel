<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Client Groups screen, to its screenshot (Leandro, 2026-09-07): the intro, the
 * Group Name / Group Colour / % Discount / Suspend-Terminate Exempt / Separate Invoices
 * grid, and the Add Client Group form beneath it.
 *
 * ## What is enforced, and what is not
 *
 * A group is a real record and a client's membership is a real property, so the Client
 * Profile's "Client Group" row finally reads something. Of the three settings a group
 * carries, one is enforced today:
 *
 *  - **Exempt from Suspend & Terminate** — read by {@see Support\ServiceOverrides}, the
 *    hourly sweep that already un-suspends services with an override date. A member's
 *    services are skipped by the same pass.
 *  - **Group Discount %** and **Separate Invoices for Services** are stored and shown but
 *    change nothing yet: the first needs a hook in price calculation and the second in
 *    invoice generation, neither of which exists here. They say so on the screen rather
 *    than looking live — a discount that silently does not apply is worse than one that
 *    is honestly marked pending.
 */
class ClientGroups extends Page
{
    protected string $view = 'adminops::pages.client-groups';

    protected static ?string $slug = 'client-groups';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public string $name = '';

    public string $colour = '#ffffff';

    public string $discount = '0';

    public bool $suspendExempt = false;

    public bool $separateInvoices = false;

    /** Set while editing an existing group; null while adding. */
    public ?int $editing = null;

    public ?int $confirming = null;

    public static function canAccess(): bool
    {
        return UserResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Client Groups';
    }

    /** The reference's own intro, verbatim. */
    public function getSubheading(): ?string
    {
        return 'Client Groups can be used to differentiate between your customers more easily '
            . 'and apply overrides to certain functions.';
    }

    public function edit(int $id): void
    {
        $group = DB::table('ext_client_groups')->find($id);

        if (!$group) {
            return;
        }

        $this->editing = $id;
        $this->name = (string) $group->name;
        $this->colour = (string) $group->colour;
        $this->discount = (string) $group->discount_percent;
        $this->suspendExempt = (bool) $group->suspend_exempt;
        $this->separateInvoices = (bool) $group->separate_invoices;
    }

    public function cancel(): void
    {
        $this->reset(['editing', 'name', 'colour', 'discount', 'suspendExempt', 'separateInvoices']);
        $this->colour = '#ffffff';
        $this->discount = '0';
    }

    public function save(): void
    {
        abort_unless(UserResource::canCreate(), 403);

        $this->validate([
            'name' => 'required|string|max:255|unique:ext_client_groups,name' . ($this->editing ? ',' . $this->editing : ''),
            'colour' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'discount' => 'required|numeric|min:0|max:100',
        ], attributes: ['name' => 'group name', 'colour' => 'group colour', 'discount' => 'group discount']);

        $row = [
            'name' => $this->name,
            'colour' => strtolower($this->colour),
            'discount_percent' => (float) $this->discount,
            'suspend_exempt' => $this->suspendExempt,
            'separate_invoices' => $this->separateInvoices,
            'updated_at' => now(),
        ];

        if ($this->editing) {
            DB::table('ext_client_groups')->where('id', $this->editing)->update($row);
        } else {
            DB::table('ext_client_groups')->insert($row + ['created_at' => now()]);
        }

        Notification::make()->title($this->editing ? 'Client group updated' : 'Client group added')->success()->send();
        $this->cancel();
    }

    public function runDelete(): void
    {
        $id = $this->confirming;
        $this->reset('confirming');

        abort_unless(UserResource::canCreate(), 403);

        DB::table('ext_client_groups')->where('id', $id)->delete();

        // Members point at the group by property; clear those or the profile would show a
        // group id that resolves to nothing.
        DB::table('properties')
            ->where('key', 'client_group_id')->where('value', (string) $id)
            ->where('model_type', \App\Models\User::class)
            ->delete();

        Notification::make()->title('Client group deleted')
            ->body('Clients that were in it are now ungrouped.')->success()->send();
    }

    /** How many clients each group holds, for the grid. */
    public static function memberCounts(): array
    {
        try {
            return DB::table('properties')
                ->where('key', 'client_group_id')
                ->where('model_type', \App\Models\User::class)
                ->selectRaw('value, count(*) as total')
                ->groupBy('value')->pluck('total', 'value')->all();
        } catch (\Throwable $exception) {
            return [];
        }
    }

    protected function getViewData(): array
    {
        return [
            'groups' => DB::table('ext_client_groups')->orderBy('name')->get(),
            'counts' => static::memberCounts(),
        ];
    }
}
