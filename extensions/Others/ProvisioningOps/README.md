# ProvisioningOps

Failed provisioning actions, visible to the admin and retryable — and a guard that stops a
failed provision from leaving an order silently "active".

Server extensions call `ProvisioningOps::failed()` / `::succeeded()` from their lifecycle
error path; both are no-ops when this module is disabled, so they can be called
unconditionally.

Admin screen: **Services → Provisioning** (nav badge shows outstanding failures).

Full documentation: [`docs/modules/provisioning-ops.md`](../../../docs/modules/provisioning-ops.md).

**Enable:** **Admin → Extensions** (runs `installed()`, which applies its migration).
