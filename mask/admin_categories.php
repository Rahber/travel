<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common.php'; // Include common.php for session and security functions
verifyAdminAccess();
includeHeader();

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                if (isset($_POST['name'])) {
                    $name = $_POST['name'];
                    $icon = $_POST['icon'];
                    $color = $_POST['color'];
                    $stmt = $conn->prepare("INSERT INTO categories (name, icon, color) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $name, $icon, $color);
                    $stmt->execute();
                    echo '<div class="alert alert-success">Category added successfully!</div>';
                }
                break;
            case 'edit':
                if (isset($_POST['category_id']) && isset($_POST['name'])) {
                    $category_id = $_POST['category_id'];
                    $name = $_POST['name'];
                    $icon = $_POST['icon'];
                    $color = $_POST['color'];
                    $stmt = $conn->prepare("UPDATE categories SET name = ?, icon = ?, color = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $name, $icon, $color, $category_id);
                    $stmt->execute();
                    echo '<div class="alert alert-success">Category updated successfully!</div>';
                }
                break;
            case 'delete':
                if (isset($_POST['category_id'])) {
                    $category_id = $_POST['category_id'];
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->bind_param("i", $category_id);
                    $stmt->execute();
                    echo '<div class="alert alert-success">Category deleted successfully!</div>';
                }
                break;
        }
    }
}

// Get all categories
$result = $conn->query("SELECT * FROM categories ORDER BY name");
$categories = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h1>Category Management</h1>
    
    <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addCategoryModal">Add New Category</button>
    
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Icon</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($categories as $category): ?>
            <tr>
                <td><?php echo htmlspecialchars($category['id']); ?></td>
                <td>
                    <i class="<?php echo htmlspecialchars($category['icon']); ?>" 
                       style="color: <?php echo htmlspecialchars($category['color']); ?>"></i>
                </td>
                <td><?php echo htmlspecialchars($category['name']); ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editCategoryModal" 
                            onclick="populateEditModal(<?php echo htmlspecialchars(json_encode($category)); ?>)">Edit</button>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteCategoryModal"
                            onclick="populateDeleteModal(<?php echo $category['id']; ?>)">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addCategoryForm" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon Class</label>
                        <input type="text" class="form-control" name="icon" placeholder="fas fa-tag" required>
                        <small class="text-muted">Use Font Awesome classes (e.g. fas fa-tag)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon Color</label>
                        <input type="color" class="form-control" name="color" required>
                    </div>
                    <button type="submit" class="btn btn-success">Add Category</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editCategoryForm" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="edit_category_id" name="category_id">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon Class</label>
                        <input type="text" class="form-control" id="edit_icon" name="icon" placeholder="fas fa-tag" required>
                        <small class="text-muted">Use Font Awesome classes (e.g. fas fa-tag)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon Color</label>
                        <input type="color" class="form-control" id="edit_color" name="color" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this category?</p>
                <form id="deleteCategoryForm" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="delete_category_id" name="category_id">
                    <button type="submit" class="btn btn-danger">Delete</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function populateEditModal(category) {
    document.getElementById('edit_category_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_icon').value = category.icon;
    document.getElementById('edit_color').value = category.color;
}

function populateDeleteModal(categoryId) {
    document.getElementById('delete_category_id').value = categoryId;
}
</script>

<?php includeFooter(); ?>     