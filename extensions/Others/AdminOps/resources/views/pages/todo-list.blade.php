{{-- To-Do List: add a task, tick it done, strike it off. --}}
<x-filament-panels::page>
    <div class="ao-mu ao-td">
        <form class="ao-find" autocomplete="off" wire:submit.prevent="add">
            <div class="ao-find-fields">
                <label class="ao-find-field ao-find-grow">
                    <span>Task</span>
                    <input type="text" wire:model="newTitle" placeholder="What needs doing?" required>
                </label>
                <label class="ao-find-field">
                    <span>Due Date</span>
                    <input type="date" wire:model="newDue">
                </label>
            </div>
            <button type="submit" class="ao-find-go">Add Task</button>
        </form>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th class="ao-mu-check">Done</th>
                    <th>Task</th>
                    <th>Due Date</th>
                    <th>Added</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($todos as $todo)
                    <tr @class(['ao-td-done' => $todo->done])>
                        <td class="ao-mu-check">
                            <input type="checkbox" @checked($todo->done) wire:click="toggle({{ $todo->id }})">
                        </td>
                        <td class="ao-mu-left">{{ $todo->title }}</td>
                        <td>{{ $todo->due_date ? \Illuminate\Support\Carbon::parse($todo->due_date)->format('m/d/Y') : '—' }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($todo->created_at)->format('m/d/Y') }}</td>
                        <td class="ao-mu-actions">
                            <button type="button" class="ao-mo-delete" title="Delete task"
                                wire:click="remove({{ $todo->id }})" wire:confirm="Delete this task?">
                                <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ao-mu-none">Nothing to do — add the first task above</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
