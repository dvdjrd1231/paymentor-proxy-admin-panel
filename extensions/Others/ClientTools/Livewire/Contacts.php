<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use App\Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\ClientTools\Models\Contact;

/** Contacts — the extra people listed on a customer's account. */
class Contacts extends Component
{
    /** Id being edited, or null while adding a new contact. */
    public ?int $editing = null;

    /** The Choose Contact select - '' is the reference's "Add New Contact". */
    public string $chosen = '';

    public array $form = self::BLANK;

    private const BLANK = [
        'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
        'company_name' => '', 'address' => '', 'address2' => '', 'city' => '', 'state' => '',
        'zip' => '', 'country' => '', 'is_sub_account' => false, 'permissions' => [],
        'email_preferences' => [],
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
            'form.address2' => 'nullable|string|max:255',
            'form.city' => 'nullable|string|max:255',
            'form.state' => 'nullable|string|max:255',
            'form.zip' => 'nullable|string|max:255',
            'form.country' => 'nullable|string|max:255',
            'form.is_sub_account' => 'boolean',
            'form.permissions' => 'array',
            'form.permissions.*' => 'in:' . implode(',', Contact::PERMISSIONS),
            'form.email_preferences' => 'array',
            'form.email_preferences.*' => 'in:' . implode(',', Contact::EMAIL_PREFERENCES),
        ];
    }

    /** The reference's Go button: load whatever the selector is on. */
    public function choose(): void
    {
        $this->resetValidation();

        if ($this->chosen === '') {
            $this->editing = null;
            $this->form = self::BLANK;

            return;
        }

        $this->edit((int) $this->chosen);
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
            'address2' => $contact->address2 ?? '',
            'city' => $contact->city ?? '',
            'state' => $contact->state ?? '',
            'zip' => $contact->zip ?? '',
            'country' => $contact->country ?? '',
            'is_sub_account' => $contact->is_sub_account,
            'permissions' => $contact->permissions ?? [],
            'email_preferences' => $contact->email_preferences ?? [],
        ];

        $this->chosen = (string) $contact->id;
        $this->resetValidation();
    }

    public function save()
    {
        $this->validate();

        $data = $this->form + ['user_id' => Auth::id()];

        if ($this->editing) {
            $this->ownedContact($this->editing)->update($data);
        } else {
            // Stay on the contact just created, as the reference does - the selector
            // moves to it rather than snapping back to Add New Contact.
            $contact = Contact::create($data);
            $this->editing = $contact->id;
            $this->chosen = (string) $contact->id;
        }

        return $this->notify(__('clienttools.contact_saved'));
    }

    public function delete(int $id)
    {
        $this->ownedContact($id)->delete();

        $this->cancel();

        return $this->notify(__('clienttools.contact_deleted'));
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->chosen = '';
        $this->form = self::BLANK;
        $this->resetValidation();
    }

    /** Fetch a contact that belongs to the signed-in user, or 404. */
    private function ownedContact(int $id): Contact
    {
        return Contact::where('user_id', Auth::id())->findOrFail($id);
    }

    /**
     * The country names offered by the Country select.
     *
     * @return array<int, string>
     */
    private function countryNames(): array
    {
        $countries = config('app.countries', []);
        unset($countries['']);

        return array_values($countries);
    }

    public function render()
    {
        return view('clienttools::contacts', [
            'contacts' => Contact::where('user_id', Auth::id())->orderBy('first_name')->get(),
            'permissionKeys' => Contact::PERMISSIONS,
            'emailPreferenceKeys' => Contact::EMAIL_PREFERENCES,
            // Core's own country list, the same one the tax rates and checkout use. The
            // names rather than the ISO keys: `country` is a free-text column whose
            // existing rows hold names, and storing codes now would make old and new
            // contacts disagree about what the column means.
            'countries' => $this->countryNames(),
        ]);
    }
}
