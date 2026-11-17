<?php
require "../config.php";
require_once "../includes/csrf.php";
require_once "../includes/notification_events.php";

if (empty($_SESSION["user_id"]) || strtolower($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

// Match the shared Admin header's live suspended-account guard before POST handling.
$auth_stmt = $conn->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
$auth_user_id = (int)$_SESSION["user_id"];
$auth_stmt->bind_param("i", $auth_user_id);
$auth_stmt->execute();
$auth_user = $auth_stmt->get_result()->fetch_assoc();
if (($auth_user["status"] ?? "active") === "suspended") {
    session_destroy();
    header("Location: ../login.php?err=suspended");
    exit;
}

$err = "";

// Load an existing product only when the requested ID is a valid integer.
$edit_mode = false;
$edit_product = null;
$edit_request_invalid = false;
$edit_id = null;
if (array_key_exists('edit_id', $_GET)) {
    $edit_id = filter_var($_GET['edit_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($edit_id === false) {
        $edit_request_invalid = true;
        $err = "ID produk tidak valid.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $edit_product = $stmt->get_result()->fetch_assoc();
            if ($edit_product) {
                $edit_mode = true;
            } else {
                $edit_request_invalid = true;
                $err = "Produk tidak ditemukan.";
            }
        } catch (Throwable $e) {
            error_log("Admin product lookup failed.");
            $edit_request_invalid = true;
            $err = "Data produk belum dapat dimuat.";
        }
    }
}

$product_form_values = [
    'name' => (string)($edit_product['name'] ?? ''),
    'description' => (string)($edit_product['description'] ?? ''),
    'price' => (string)($edit_product['price'] ?? ''),
    'category_id' => (string)($edit_product['category_id'] ?? ''),
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "add";

    if ($action === "edit") {

        $edit_request_invalid = true;
        $posted_edit_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($posted_edit_id === false) {
            $err = "ID produk tidak valid. Tidak ada perubahan yang dilakukan.";
        } else {
            try {
                $lookup = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
                $lookup->bind_param("i", $posted_edit_id);
                $lookup->execute();
                $posted_product = $lookup->get_result()->fetch_assoc();
            } catch (Throwable $e) {
                error_log("Admin product edit lookup failed.");
                $posted_product = null;
            }

            if (!$posted_product) {
                $err = "Produk tidak ditemukan. Tidak ada perubahan yang dilakukan.";
            } else {
                $edit_mode = true;
                $edit_request_invalid = false;
                $edit_product = $posted_product;
                foreach (['name', 'description', 'price', 'category_id'] as $field) {
                    $value = $_POST[$field] ?? null;
                    if (is_scalar($value)) $product_form_values[$field] = (string)$value;
                }

                $name_value = $_POST['name'] ?? null;
                $description_value = $_POST['description'] ?? null;
                $name = is_string($name_value) ? trim($name_value) : '';
                $description = is_string($description_value) ? trim($description_value) : '';
                $price_input = $_POST['price'] ?? null;
                $category_id = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $price_parts = [];

                if (preg_match('/\A.{1,100}\z/us', $name) !== 1) {
                    $err = "Nama produk wajib diisi dan maksimal 100 karakter.";
                } elseif (strlen($description) > 65535 || preg_match('//u', $description) !== 1) {
                    $err = "Deskripsi produk tidak valid atau terlalu panjang.";
                } elseif (!is_string($price_input) || preg_match('/\A([0-9]{1,8})(?:\.([0-9]{1,2}))?\z/D', trim($price_input), $price_parts) !== 1) {
                    $err = "Harga tidak valid. Masukkan angka positif dengan maksimal dua angka desimal.";
                } elseif ($category_id === false) {
                    $err = "Kategori produk tidak valid.";
                } else {
                    $whole_price = (int)$price_parts[1];
                    $fraction = str_pad($price_parts[2] ?? '', 2, '0');
                    $price_cents = ($whole_price * 100) + (int)$fraction;
                    if ($price_cents <= 0) {
                        $err = "Harga harus lebih besar dari nol.";
                    }
                    $price = sprintf('%d.%02d', $whole_price, (int)$fraction);

                    if ($err === '') {
                        try {
                            $category_stmt = $conn->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
                            $category_stmt->bind_param("i", $category_id);
                            $category_stmt->execute();
                            if (!$category_stmt->get_result()->fetch_assoc()) {
                                $err = "Kategori tidak ditemukan.";
                            }
                        } catch (Throwable $e) {
                            error_log("Admin product category validation failed.");
                            $err = "Kategori produk belum dapat divalidasi.";
                        }
                    }
                }

                $image = (string)($posted_product['image'] ?? '');
                $new_image_path = null;
                $upload = $_FILES['image'] ?? null;
                if ($err === '' && is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        $err = "Gambar produk gagal diunggah.";
                    } elseif (($upload['size'] ?? 0) > 5 * 1024 * 1024) {
                        $err = "Ukuran gambar maksimal 5 MB.";
                    } else {
                        $allowed_mimes = [
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'jfif' => 'image/jpeg',
                            'png' => 'image/png',
                            'webp' => 'image/webp',
                        ];
                        $extension = strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
                        $mime = '';
                        try {
                            $file_info = new finfo(FILEINFO_MIME_TYPE);
                            $mime = $file_info->file((string)($upload['tmp_name'] ?? '')) ?: '';
                        } catch (Throwable $e) {
                            $mime = '';
                        }
                        if (!isset($allowed_mimes[$extension]) || $allowed_mimes[$extension] !== $mime) {
                            $err = "Format gambar tidak didukung.";
                        } else {
                            $upload_directory = __DIR__ . '/../uploads/products';
                            if (!is_dir($upload_directory) && !mkdir($upload_directory, 0775, true) && !is_dir($upload_directory)) {
                                $err = "Folder penyimpanan gambar belum tersedia.";
                            } else {
                                try {
                                    $new_image_name = 'prod_' . bin2hex(random_bytes(16)) . '.' . $extension;
                                    $new_image_path = $upload_directory . DIRECTORY_SEPARATOR . $new_image_name;
                                    if (move_uploaded_file((string)$upload['tmp_name'], $new_image_path)) {
                                        $image = $new_image_name;
                                    } else {
                                        $new_image_path = null;
                                        $err = "Gambar produk gagal disimpan.";
                                    }
                                } catch (Throwable $e) {
                                    $new_image_path = null;
                                    $err = "Gambar produk gagal disimpan.";
                                }
                            }
                        }
                    }
                }

                if ($err === '') {
                    $transaction_started = false;
                    try {
                        if (!$conn->begin_transaction()) {
                            throw new RuntimeException("Product update transaction could not start.");
                        }
                        $transaction_started = true;

                        $lock_stmt = $conn->prepare("SELECT price, image FROM products WHERE id = ? FOR UPDATE");
                        $lock_stmt->bind_param("i", $posted_edit_id);
                        $lock_stmt->execute();
                        $current_product = $lock_stmt->get_result()->fetch_assoc();
                        if (!$current_product) {
                            throw new RuntimeException("Product no longer exists.");
                        }

                        $old_price_parts = [];
                        if (preg_match('/\A([0-9]+)(?:\.([0-9]{1,2}))?\z/', (string)$current_product['price'], $old_price_parts) !== 1) {
                            throw new RuntimeException("Stored product price is invalid.");
                        }
                        $old_price = sprintf('%d.%02d', (int)$old_price_parts[1], (int)str_pad($old_price_parts[2] ?? '', 2, '0'));
                        $old_price_cents = ((int)$old_price_parts[1] * 100) + (int)str_pad($old_price_parts[2] ?? '', 2, '0');
                        $price_changed = $old_price_cents !== $price_cents;

                        $update_stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, image = ? WHERE id = ?");
                        $update_stmt->bind_param("sssisi", $name, $description, $price, $category_id, $image, $posted_edit_id);
                        if (!$update_stmt->execute()) {
                            throw new RuntimeException("Product update failed.");
                        }

                        if ($price_changed) {
                            $format_rupiah = static function (string $amount): string {
                                $parts = explode('.', $amount, 2);
                                $fraction = rtrim($parts[1] ?? '', '0');
                                return 'Rp' . number_format((int)$parts[0], 0, ',', '.') . ($fraction !== '' ? ',' . $fraction : '');
                            };
                            $notification_body = 'Harga produk ' . $name . ' berubah dari ' . $format_rupiah($old_price) . ' menjadi ' . $format_rupiah($price) . '.';
                            if (!notification_event_send_active_customers($conn, 'Perubahan Harga Produk', $notification_body, 'info')) {
                                throw new RuntimeException("Product price notification failed.");
                            }
                        }

                        if (!$conn->commit()) {
                            throw new RuntimeException("Product update transaction could not commit.");
                        }
                        $transaction_started = false;

                        if (!empty($new_image_path) && !empty($current_product['image'])) {
                            $old_image_name = basename((string)$current_product['image']);
                            if ($old_image_name === (string)$current_product['image']) {
                                try {
                                    $image_check = $conn->prepare("SELECT COUNT(*) AS c FROM products WHERE image = ?");
                                    $image_check->bind_param("s", $old_image_name);
                                    $image_check->execute();
                                    if ((int)$image_check->get_result()->fetch_assoc()['c'] === 0) {
                                        $old_image_path = __DIR__ . '/../uploads/products/' . $old_image_name;
                                        if (is_file($old_image_path)) unlink($old_image_path);
                                    }
                                } catch (Throwable $e) {
                                    error_log("Old product image cleanup failed.");
                                }
                            }
                        }

                        $_SESSION['product_success'] = ['action' => 'edit', 'name' => $name];
                        header("Location: products.php");
                        exit;
                    } catch (Throwable $e) {
                        if ($transaction_started) {
                            try { $conn->rollback(); } catch (Throwable $rollback_error) { error_log("Product update rollback failed."); }
                        }
                        if (!empty($new_image_path) && is_file($new_image_path)) unlink($new_image_path);
                        error_log("Admin product update failed.");
                        $err = "Perubahan produk gagal disimpan. Silakan periksa data dan coba kembali.";
                    }
                }
            }
        }

    } elseif ($action === "add") {
        $name = $_POST["name"];
        $price = (float)$_POST["price"];
        $cat = (int)$_POST["category_id"];

        $image = "";
        if (isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) die("Error: Gagal upload file.");
            $allowed = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp", "jfif" => "image/jpeg"];
            $filename = $_FILES["image"]["name"];
            $filesize = $_FILES["image"]["size"];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!array_key_exists($ext, $allowed)) die("Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, WEBP, atau JFIF.");
            if ($filesize > 5 * 1024 * 1024) die("Error: Ukuran file lebih dari 5MB.");

            $new_filename = uniqid("prod_", true) . "." . $ext;
            $upload_path = "../uploads/products/" . $new_filename;

            if (!is_dir("../uploads/products")) {
                mkdir("../uploads/products", 0777, true);
            }

            if (move_uploaded_file($_FILES["image"]["tmp_name"], $upload_path)) {
                if ($edit_mode && !empty($edit_product['image']) && file_exists("../uploads/products/" . $edit_product['image'])) {
                    // Cek jika gambar dipakai produk lain sebelum hapus
                    $chk = $conn->prepare("SELECT COUNT(*) as c FROM products WHERE image = ? AND id != ?");
                    $chk->bind_param("si", $edit_product['image'], $edit_product['id']);
                    $chk->execute();
                    if ($chk->get_result()->fetch_assoc()['c'] == 0) {
                        unlink("../uploads/products/" . $edit_product['image']);
                    }
                }
                $image = $new_filename;
            }
        }


            $stmt = $conn->prepare("INSERT INTO products (name, price, category_id, image) VALUES (?, ?, ?, ?)");
            if (!$stmt) { $err = "Gagal menambah produk."; }
            if (!$stmt) { goto product_add_done; }
            $stmt->bind_param("sdis", $name, $price, $cat, $image);
            if ($stmt->execute()) {
                $pid = $conn->insert_id;
                $stock_stmt = $conn->prepare("INSERT INTO stock (product_id, current_stock) VALUES (?, 0)");
                $stock_ok = false;
                if ($stock_stmt) {
                    $stock_stmt->bind_param("i", $pid);
                    $stock_ok = $stock_stmt->execute();
                }
                if (!$stock_ok) {
                    error_log("Admin product stock initialization failed.");
                    $err = "Produk tersimpan, tetapi inisialisasi stok gagal. Produk belum diumumkan.";
                } else {
                    $notice_ok = false;
                    try {
                        if ($conn->begin_transaction()) {
                            $notice_ok = notification_event_send_active_customers($conn, 'Produk baru', 'Produk baru tersedia: ' . $name . '.', 'info');
                            if ($notice_ok) $notice_ok = $conn->commit();
                            if (!$notice_ok) $conn->rollback();
                        }
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $notice_ok = false;
                    }
                    if (!$notice_ok) error_log("New product notification broadcast failed.");
                }
                if ($stock_ok) {
                    $_SESSION['product_success'] = ['action' => 'add', 'name' => $name];
                    header("Location: products.php");
                    exit;
                }
            } else {
                $err = "Gagal menambah produk.";
            }
        product_add_done:
    } elseif ($action === "delete") {

        $del_id = filter_var($_POST["product_id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        if ($del_id === false) {
            $_SESSION["ui_notification"] = ["type" => "error", "message" => "Produk tidak ditemukan atau data penghapusan tidak valid."];
            header("Location: products.php");
            exit;
        }
        $product_stmt = $conn->prepare("SELECT image FROM products WHERE id = ? LIMIT 1");
        $product_stmt->bind_param("i", $del_id);
        $product_stmt->execute();
        $delete_product = $product_stmt->get_result()->fetch_assoc();
        if (!$delete_product) {
            $_SESSION["ui_notification"] = ["type" => "error", "message" => "Produk tidak ditemukan. Tidak ada perubahan yang dilakukan."];
            header("Location: products.php");
            exit;
        }
        $delete_succeeded = false;
        $delete_message = "Produk tidak berhasil dihapus. Silakan coba kembali.";

        // Cek histori transaksi
        $hist_order = 0; $hist_po = 0; $hist_stock = 0;

        $stmt1 = $conn->prepare("SELECT COUNT(*) as c FROM order_items WHERE product_id = ?");
        $stmt1->bind_param("i", $del_id);
        $stmt1->execute();
        $hist_order = $stmt1->get_result()->fetch_assoc()['c'];

        $stmt2 = $conn->prepare("SELECT COUNT(*) as c FROM purchase_order_items WHERE product_id = ?");
        $stmt2->bind_param("i", $del_id);
        $stmt2->execute();
        $hist_po = $stmt2->get_result()->fetch_assoc()['c'];

        // Cek jika produk punya transaksi di stock_transactions
        $stmt3 = $conn->prepare("SELECT COUNT(*) as c FROM stock_transactions st JOIN stock s ON st.stock_id = s.id WHERE s.product_id = ?");
        $stmt3->bind_param("i", $del_id);
        $stmt3->execute();
        $hist_stock = $stmt3->get_result()->fetch_assoc()['c'];

        if ($hist_order > 0 || $hist_po > 0 || $hist_stock > 0) {
            // Soft delete
            $stmt = $conn->prepare("UPDATE products SET is_available = 0 WHERE id = ?");
            $stmt->bind_param("i", $del_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $delete_succeeded = true;
                $delete_message = "Produk dinonaktifkan karena sudah digunakan dalam transaksi.";
            }
        } else {
            // Hard delete
            // Hapus stock terlebih dahulu karena foreign key
            $conn->query("DELETE FROM stock WHERE product_id = $del_id");

            // Hapus gambar jika ada
            $del_img = $delete_product['image'];
            if (!empty($del_img) && file_exists("../uploads/products/" . $del_img)) {
                $chk = $conn->prepare("SELECT COUNT(*) as c FROM products WHERE image = ? AND id != ?");
                $chk->bind_param("si", $del_img, $del_id);
                $chk->execute();
                if ($chk->get_result()->fetch_assoc()['c'] == 0) {
                    unlink("../uploads/products/" . $del_img);
                }
            }

            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->bind_param("i", $del_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $delete_succeeded = true;
                $delete_message = "Produk berhasil dihapus secara permanen.";
            }
        }
        $_SESSION["ui_notification"] = [
            "type" => $delete_succeeded ? "success" : "error",
            "message" => $delete_message
        ];
        header("Location: products.php");
        exit;

    }
}

$products = $conn->query("SELECT p.*, c.name as cname FROM products p JOIN categories c ON p.category_id = c.id WHERE p.is_available = 1 ORDER BY p.id DESC");
$cats = $conn->query("SELECT * FROM categories ORDER BY name ASC");

$success_flash = $_SESSION['product_success'] ?? null;
unset($_SESSION['product_success']);
$success_action = is_array($success_flash) && in_array($success_flash['action'] ?? '', ['add', 'edit'], true)
    ? $success_flash['action']
    : null;
$success_name = $success_action !== null
    ? htmlspecialchars((string)($success_flash['name'] ?? ''), ENT_QUOTES, 'UTF-8')
    : '';

include "header.php";
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">Katalog &amp; Master Produk</h1>
        <p class="text-xs text-stone-400 mt-1">Kelola daftar menu minuman, kudapan, dan harga jual Kopi Mahasiswa.</p>
    </div>
</div>

<?php if ($success_action !== null): ?>
<style>
    @keyframes productSuccessFade {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes productSuccessScale {
        from { opacity: 0; transform: scale(.96) translateY(8px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    @keyframes productSuccessDraw {
        to { stroke-dashoffset: 0; }
    }
    .product-success-overlay {
        animation: productSuccessFade .22s ease-out both;
    }
    .product-success-panel {
        animation: productSuccessScale .3s cubic-bezier(.2,.75,.25,1) both;
    }
    .product-success-check {
        stroke-dasharray: 48;
        stroke-dashoffset: 48;
        animation: productSuccessDraw .55s .16s ease-out forwards;
    }
    @media (prefers-reduced-motion: reduce) {
        .product-success-overlay,
        .product-success-panel,
        .product-success-check {
            animation: none;
        }
        .product-success-check {
            stroke-dashoffset: 0;
        }
    }
</style>
<div id="product-success-modal" class="product-success-overlay fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-black/75 px-4 py-6 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="product-success-title">
    <section class="product-success-panel relative w-full max-w-lg rounded-3xl border border-emerald-400/20 bg-[#101722] p-6 text-center shadow-[0_28px_90px_rgba(0,0,0,0.65)] sm:p-9">
        <button type="button" id="product-success-close" aria-label="Tutup konfirmasi" class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-xl border border-stone-700 bg-white/5 text-stone-400 transition hover:bg-white/10 hover:text-white">
            <i data-lucide="x" class="h-4 w-4"></i>
        </button>
        <div class="mx-auto max-w-md pt-3">
            <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300/80">Penyimpanan berhasil</p>
            <h2 id="product-success-title" class="mt-3 text-2xl font-extrabold leading-tight text-white sm:text-3xl">
                <?= $success_action === 'add' ? 'Produk Berhasil Ditambahkan!' : 'Perubahan Produk Berhasil Disimpan!' ?>
            </h2>
            <div class="mx-auto mt-6 flex h-20 w-20 items-center justify-center rounded-full border border-emerald-400/30 bg-emerald-400/10 shadow-[0_0_36px_rgba(52,211,153,0.12)]" aria-hidden="true">
                <svg class="h-11 w-11" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path class="product-success-check" d="M11 25L20 34L38 15" stroke="#6EE7B7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <p class="mx-auto mt-5 max-w-sm text-sm leading-6 text-stone-300">
                <?php if ($success_action === 'add'): ?>
                    Produk <strong class="font-semibold text-white"><?= $success_name ?></strong> berhasil disimpan dan siap dikelola.
                <?php else: ?>
                    Perubahan pada <strong class="font-semibold text-white"><?= $success_name ?></strong> berhasil disimpan.
                <?php endif; ?>
            </p>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="products.php#product-list" class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-400 to-emerald-500 px-5 py-3 text-sm font-bold text-[#07130f] shadow-lg shadow-emerald-950/40 transition hover:from-emerald-300 hover:to-emerald-400">
                    <i data-lucide="list" class="h-4 w-4"></i>
                    Lihat Daftar Produk
                </a>
                <a href="products.php" class="inline-flex items-center justify-center gap-2 rounded-xl border border-stone-700 bg-white/5 px-5 py-3 text-sm font-semibold text-stone-200 transition hover:bg-white/10 hover:text-white">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Tambah Produk Lagi
                </a>
            </div>
            <noscript>
                <p class="mt-4 text-xs text-stone-500">Gunakan tombol navigasi di atas untuk melanjutkan.</p>
            </noscript>
        </div>
    </section>
</div>
<script>
    document.getElementById('product-success-close')?.addEventListener('click', function() {
        document.getElementById('product-success-modal')?.remove();
    });
    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
<?php endif; ?>

<?php if ($err): ?>
<div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-3 rounded-xl text-xs mb-5 flex items-center gap-2">
    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-400"></i>
    <span><?= htmlspecialchars($err) ?></span>
</div>
<?php endif; ?>

<?php if (!$edit_request_invalid): ?>
<!-- Product Form -->
<form method="POST" data-loading enctype="multipart/form-data" class="bg-[#0e131d]/90 border border-stone-800/80 p-6 rounded-2xl shadow-xl mb-8">
    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-stone-800/80">
        <i data-lucide="<?= $edit_mode ? 'edit' : 'plus-circle' ?>" class="w-4 h-4 text-amber-400"></i>
        <h2 class="text-xs font-bold text-white uppercase tracking-wider"><?= $edit_mode ? 'Perbarui Data Produk' : 'Tambah Produk Baru' ?></h2>
    </div>

    <?= csrf_input(); ?>
    <input type="hidden" name="action" value="<?= $edit_mode ? 'edit' : 'add' ?>">
    <?php if ($edit_mode): ?><input type="hidden" name="product_id" value="<?= (int)$edit_product['id'] ?>"><?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Nama Produk</label>
            <input type="text" name="name" value="<?= htmlspecialchars($product_form_values['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" maxlength="100" required
                   class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl px-3.5 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none"
                   placeholder="Contoh: Kopi Susu Aren">
        </div>

        <div>
            <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Harga Jual (Rp)</label>
            <input type="number" name="price" value="<?= htmlspecialchars($product_form_values['price'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" min="0.01" max="99999999.99" step="0.01" required
                   class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl px-3.5 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none"
                   placeholder="18000">
        </div>

        <div>
            <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Kategori</label>
            <select name="category_id" class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl px-3.5 py-2.5 text-sm text-stone-200 transition-all outline-none">
                <?php $cats->data_seek(0); while($c = $cats->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= $edit_mode && $product_form_values['category_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">
                Gambar <?= $edit_mode ? '<span class="text-stone-500 lowercase font-normal">(opsional)</span>' : '' ?>
            </label>
            <input type="file" name="image" accept="image/jpeg, image/png, image/webp, image/jfif"
                   class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 rounded-xl px-3 py-1.5 text-xs text-stone-300 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-stone-800 file:text-stone-300 hover:file:bg-stone-700">
        </div>
    </div>


    <?php if ($edit_mode): ?>
    <div class="mt-4">
        <label for="product-description" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-stone-300">Deskripsi</label>
        <textarea id="product-description" name="description" rows="3" maxlength="65535" class="w-full resize-y rounded-xl border border-stone-800 bg-[#131926] px-3.5 py-2.5 text-sm text-stone-200 outline-none transition-all focus:border-amber-500 focus:ring-1 focus:ring-amber-500"><?= htmlspecialchars($product_form_values['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
    </div>
    <?php endif; ?>

    <div class="mt-5 flex items-center gap-3">
        <button type="submit" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-6 py-2.5 rounded-xl text-sm transition-all shadow-md shadow-amber-950/40 flex items-center gap-1.5">
            <i data-lucide="<?= $edit_mode ? 'save' : 'plus' ?>" class="w-4 h-4"></i> <?= $edit_mode ? 'Simpan Perubahan' : 'Tambah Produk' ?>
        </button>
        <?php if($edit_mode): ?>
            <a href="products.php" class="bg-[#1a2233] hover:bg-[#232d42] text-stone-300 border border-stone-700 px-5 py-2.5 rounded-xl text-sm transition-all">
                Batal
            </a>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>

<!-- Products Table -->
<div id="product-list" class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
    <div class="p-4 border-b border-stone-800/80 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i data-lucide="package" class="w-4 h-4 text-amber-400"></i>
            <h2 class="text-xs font-bold text-white uppercase tracking-wider">Daftar Produk Tersedia</h2>
        </div>
        <span class="text-xs text-stone-500 font-mono"><?= $products->num_rows ?> Produk Aktif</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                <tr>
                    <th class="p-4 w-20">Foto</th>
                    <th class="p-4">Nama Produk</th>
                    <th class="p-4">Kategori</th>
                    <th class="p-4">Harga Satuan</th>
                    <th class="p-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-800/60">
                <?php while($p = $products->fetch_assoc()): ?>
                <tr class="hover:bg-stone-800/30 transition-colors">
                    <td class="p-4">
                        <?php if (!empty($p['image']) && file_exists("../uploads/products/" . $p['image'])): ?>
                            <img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="w-12 h-12 object-cover rounded-xl border border-stone-800">
                        <?php else: ?>
                            <div class="w-12 h-12 bg-stone-900 border border-stone-800 text-stone-500 text-[10px] flex items-center justify-center rounded-xl font-medium">No Image</div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 font-bold text-white text-sm"><?= htmlspecialchars($p['name']) ?></td>
                    <td class="p-4 text-stone-400">
                        <span class="bg-[#141b29] border border-stone-800 px-2.5 py-1 rounded-md text-[11px]">
                            <?= htmlspecialchars($p['cname']) ?>
                        </span>
                    </td>
                    <td class="p-4 text-amber-400 font-mono font-bold text-sm">Rp <?= number_format($p['price'],0,',','.') ?></td>
                    <td class="p-4 text-center">
                        <div class="flex justify-center items-center gap-2">
                            <a href="products.php?edit_id=<?= $p['id'] ?>" class="bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1">
                                <i data-lucide="edit" class="w-3.5 h-3.5"></i> Edit
                            </a>
                            <form method="POST" class="inline" data-loading data-confirm data-confirm-title="Hapus produk" data-confirm-message="Produk “{object}” akan dihapus, atau dinonaktifkan jika sudah memiliki riwayat. Lanjutkan?" data-confirm-object="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>" data-confirm-label="Ya, lanjutkan" data-confirm-danger="true">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "footer.php"; ?>
