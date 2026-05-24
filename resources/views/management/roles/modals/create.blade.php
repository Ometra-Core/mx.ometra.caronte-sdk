<div class="modal fade" id="roleCreateModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="roleCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="roleCreateModalLabel">Create new role</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('caronte.management.roles.create') }}" id="roleCreateForm">
                    @csrf
                    <div class="mb-3">
                        <label for="roleNameInput" class="form-label fw-semibold">Role Name:</label>
                        <input name="name" type="text" class="form-control" id="roleNameInput" required>
                        <small class="form-text text-muted">Use letters, numbers, dots, underscores, or hyphens; no spaces (e.g., admin, editor.team, moderator)</small>
                    </div>
                    <div class="mb-3">
                        <label for="roleDescriptionInput" class="form-label fw-semibold">Description:</label>
                        <textarea name="description" class="form-control" id="roleDescriptionInput" rows="3"></textarea>
                        <small class="form-text text-muted">Optional: Brief description of role purpose</small>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-plus me-2"></i>Create Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
