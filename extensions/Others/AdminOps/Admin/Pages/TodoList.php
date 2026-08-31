<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/** WHMCS's To-Do List: what staff mean to get to, with a due date and a done tick. */
class TodoList extends Page
{
    protected string $view = 'adminops::pages.todo-list';

    protected static ?string $slug = 'to-do-list';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public string $newTitle = '';

    public string $newDue = '';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'To-Do List';
    }

    public function add(): void
    {
        $this->validate(['newTitle' => 'required|string|max:255', 'newDue' => 'nullable|date'],
            attributes: ['newTitle' => 'task']);

        DB::table('ext_todos')->insert([
            'title' => $this->newTitle,
            'due_date' => $this->newDue ?: null,
            'admin_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->newTitle = '';
        $this->newDue = '';
        Notification::make()->title('Task added')->success()->send();
    }

    public function toggle(int $id): void
    {
        DB::table('ext_todos')->where('id', $id)->update(['done' => DB::raw('NOT done'), 'updated_at' => now()]);
    }

    public function remove(int $id): void
    {
        DB::table('ext_todos')->where('id', $id)->delete();
    }

    protected function getViewData(): array
    {
        return [
            'todos' => DB::table('ext_todos')
                ->orderBy('done')->orderByRaw('due_date is null')->orderBy('due_date')->orderByDesc('id')
                ->get(),
        ];
    }
}
