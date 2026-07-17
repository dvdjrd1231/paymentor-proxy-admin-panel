# Brazilian Customer Registration

Adds Brazilian tax fields to the customer **registration** and **account** forms, with CPF/CNPJ
validation, input masks, encrypted storage of sensitive documents, and access-control
permissions.

- **Location:** `extensions/Others/BrazilianRegistration/`
- **Type:** Other (cross-cutting)
- **Approach:** built on Paymenter's native **Custom Properties** system — **no core edits**.

## Fields

| Key | Field | Type | Validation | Encrypted |
|---|---|---|---|---|
| `person_type` | Person Type / Tipo de Pessoa | select (Individual / Business) | required | — |
| `cpf` | CPF | string | `cpf` (checksum) | **yes** |
| `rg` | RG | string | max:20 | **yes** |
| `company_name` | Company Name / Razão Social | string | max:191 | — |
| `trade_name` | Trade Name / Nome Fantasia | string | max:191 | — |
| `cnpj` | CNPJ | string | `cnpj` (checksum) | **yes** |
| `state_registration` | State Registration / Inscrição Estadual | string | max:30 | — |
| `state_registration_exempt` | SR Exempt / Isento de IE | checkbox | — | — |

The fields render automatically on `/register` and the client **Account** page via the core
`<x-form.properties>` component, and appear per-user in the admin **Users → Properties** relation.

## How it works

- **Fields** are seeded as Custom Property definitions (`model = User`) by the extension's
  migration when it is enabled; removed (with their stored values) when disabled.
- **Validation** — the extension registers `cpf` and `cnpj` Laravel validation rules (real
  check-digit algorithms) in `boot()`, with English + `pt_BR` messages. The seeded fields
  reference them via their `validation` column, so validation runs server-side on both the
  registration and account forms.
- **Encryption at rest** — CPF, RG, and CNPJ are encrypted transparently via Eloquent
  `saving`/`retrieved` events attached to the core `Property` model (no core edit). The database
  stores ciphertext; forms and validation continue to see plaintext. Uses Laravel's `APP_KEY`.
- **Input masks** — a small script injected through the theme's `footer` render hook formats CPF
  (`000.000.000-00`) and CNPJ (`00.000.000/0000-00`) as the user types. Purely cosmetic;
  server-side validation is authoritative.
- **Access control** — registers the `admin.brazilian.view_documents` permission for gating who
  may view sensitive documents in admin.

## Installation

Enable **Brazilian Customer Registration** in **Admin → Extensions** (this runs the migration
that seeds the fields). Disabling/uninstalling removes the fields and their stored values.

## Verified behaviour

- Fields render on `/register` with `wire:model="properties.<key>"`.
- `cpf`/`cnpj` reject invalid check digits and repeated-digit sequences; valid documents pass.
- Raw DB value for `cpf`/`rg`/`cnpj` is ciphertext; Eloquent reads back plaintext.

## Notes & limitations

- **Conditional requirement** (require CPF for individuals, CNPJ for businesses) is not enforced
  by the native property system — all documents are `nullable` but validated when present. A
  cross-field rule can be added later via a registration event listener if hard enforcement is
  required.
- The native Custom Properties renderer shows all fields on the form regardless of
  `person_type`; conditional show/hide is a client-area theme enhancement (planned with the
  custom theme).
- Encryption relies on `APP_KEY`; keep it backed up (rotating it makes stored documents
  unreadable — re-encrypt if you rotate).
