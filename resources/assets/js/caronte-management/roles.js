/**
 * Handles role CRUD modal interactions.
 *
 * Manages create, edit, and delete role modals with data population and form submission.
 *
 * PHP 8.1+
 *
 * @package   Ometra\CaronteClient
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/ometra/caronte-client Documentation
 */
document.addEventListener("DOMContentLoaded", function () {
    // ===============================================
    // ROLE EDIT MODAL
    // ===============================================
    const roleEditModal = document.getElementById("roleEditModal");
    if (roleEditModal) {
        roleEditModal.addEventListener("show.bs.modal", function (event) {
            const button = event.relatedTarget;
            const roleUri = button.getAttribute("data-role-uri");
            const roleName = button.getAttribute("data-role-name");
            const roleDescription = button.getAttribute(
                "data-role-description",
            );

            // Populate form fields
            document.getElementById("editRoleUri").value = roleUri;
            document.getElementById("roleNameDisplay").value = roleName;
            document.getElementById("roleDescriptionUpdate").value =
                roleDescription || "";
        });
    }

    // ===============================================
    // ROLE DELETE MODAL
    // ===============================================
    const roleDeleteModal = document.getElementById("roleDeleteModal");
    if (roleDeleteModal) {
        roleDeleteModal.addEventListener("show.bs.modal", function (event) {
            const button = event.relatedTarget;
            const roleUri = button.getAttribute("data-role-uri");
            const roleName = button.getAttribute("data-role-name");

            // Populate confirmation message and form
            document.getElementById("deleteRoleUri").value = roleUri;
            document.getElementById("roleNameDelete").textContent = roleName;
        });
    }

    // ===============================================
    // FORM VALIDATION (optional - client-side)
    // ===============================================
    const roleCreateForm = document.getElementById("roleCreateForm");
    if (roleCreateForm) {
        roleCreateForm.addEventListener("submit", function (event) {
            const roleNameInput = document.getElementById("roleNameInput");
            const roleName = roleNameInput.value.trim();

            // Validate allowed role name characters (no spaces)
            const alphanumericRegex = /^[a-zA-Z0-9_.-]+$/;
            if (!alphanumericRegex.test(roleName)) {
                event.preventDefault();
                alert(
                    "Role name must contain only letters, numbers, dots, underscores, or hyphens.",
                );
                roleNameInput.focus();
                return false;
            }
        });
    }
});
