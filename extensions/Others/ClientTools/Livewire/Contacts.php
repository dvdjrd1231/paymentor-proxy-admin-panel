<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use App\Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\ClientTools\Models\Contact;

/**
 * Contacts — the extra people listed on a customer's account.
 *
 * Every query is scoped to the signed-in user, and an edit re-fetches the row by
 * (id, user_id) rather than trusting the id in the request, so one customer cannot read
 * or overwrite another's contact by editing the form payload.
 */
class Contacts extends Component
{
    /** Id being edited, or null while adding a new contact. */
    public ?int $editing = null;

    public bool $showForm = false;

    public array $form = [
        'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
        'company_name' => '', 'address' => '', 'city' => '', 'state' => '',
        'zip' => '', 'country' => '', 'is_sub_account' => false, 'permissions' => [],
    ];

    protected function rules(): array
    {
        return [
            'form.first_name' => 'required|string|max:255',
            'form.last_name' => 'required|string|max:255',
            'form.email' => 'required|email|max:255',
            'form.phone' => 'nullable|string|max:255',
            'form.company_name' => 'nullable|string|max:255',
            'form.address' => 'nullable|string|max:255',
            'form.city' => 'nullable|string|max:255',
            'form.state' => 'nullable|string|max:255',
            'form.zip' => 'nullable|string|max:255',
            'form.country' => 'nullable|string|max:255',
            'form.is_sub_account' => 'boolean',
            'form.permissions' => 'array',
            'form.permissions.*' => 'in:' . implode(',', Contact::PERMISSIONS),
        ];
    }

    public function newContact(): void
    {
        $this->reset('form', 'editing');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $contact = $this->ownedContact($id);

        $this->editing = $contact->id;
        $this->form = [
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'phone' => $contact->phone ?? '',
            'company_name' => $contact->company_name ?? '',
            'address' => $contact->address ?? '',
            'city' => $contact->city ?? '',
            'state' => $contact->state ?? '',
            'zip' => $contact->zip ?? '',
            'country' => $contact->country ?? '',
            'is_sub_account' => $contact->is_sub_account,
            'permissions' => $contact->permissions ?? [],
        ];

        $this->resetValidation();
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate();

        $data = $this->form + ['user_id' => Auth::id()];

        if ($this->editing) {
            $this->ownedContact($this->editing)->update($data);
        } else {
            Contact::create($data);
        }

        $this->showForm = false;
        $this->reset('form', 'editing');

        return $this->notify(__('clienttools.contact_saved'));
    }

    public function delete(int $id)
    {
        $this->ownedContact($id)->delete();

        return $this->notify(__('clienttools.contact_deleted'));
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset('form', 'editing');
        $this->resetValidation();
    }

    /**
     * Fetch a contact that belongs to the signed-in user, or 404.
     *
     * Scoping on user_id here (rather than only on the listing query) is what stops an
     * id swapped into the request from touching someone else's row.
     */
    private function ownedContact(int $id): Contact
    {
        return Contact::where('user_id', Auth::id())->findOrFail($id);
    }

    public function render()
    {
        return view('clienttools::contacts', [
            'contacts' => Contact::where('user_id', Auth::id())->orderBy('first_name')->get(),
            'permissionKeys' => Contact::PERMISSIONS,
        ]);
    }
}
