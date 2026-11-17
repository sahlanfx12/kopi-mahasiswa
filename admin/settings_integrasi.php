<?php
/**
 * Admin — Konfigurasi Integrasi
 * A. Chatbot AI (xKiro): API Key & model aktif.
 * B. WhatsApp Gateway (Fonnte): profil device pengirim & device aktif.
 *
 * Rahasia hanya diterima lewat POST, dienkripsi di server, dan tidak pernah dirender kembali.
 */
require "../config.php"; require_once "../includes/csrf.php";
require_once "../includes/interaction_ui.php";
require_once "../includes/ai_service.php";
require_once "../includes/integration_settings.php";

if (empty($_SESSION["user_id"]) || strtolower($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}
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

function si_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function si_flash(string $type, string $message): void
{
    $_SESSION["ui_notification"] = ["type" => $type, "message" => $message];
}

/* ------------------------------------------------------------------ */
/* POST handler (CSRF sudah divalidasi otomatis oleh includes/csrf.php) */
/* ------------------------------------------------------------------ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = (string)($_POST["action"] ?? "");
    $admin_id = (int)$_SESSION["user_id"];
    $encryption = integration_encryption_status();
    $models = integration_xkiro_models();

    try {
        if (!integration_tables_ready()) {
            si_flash("error", "Tabel konfigurasi integrasi belum ada. Terapkan migrasi database terlebih dahulu.");
        } else switch ($action) {

            /* ---------- xKiro: simpan / ganti API Key ---------- */
            case "xkiro_save_key":
                $new_key = trim((string)($_POST["api_key"] ?? ""));
                if (!$encryption["ok"]) {
                    si_flash("error", "Enkripsi belum siap (INTEGRATION_ENCRYPTION_KEY). API Key tidak disimpan.");
                    break;
                }
                if (!integration_secret_is_well_formed($new_key, 16, 512)) {
                    si_flash("error", "Format API Key tidak valid (minimal 16 karakter, tanpa spasi).");
                    break;
                }
                $slug = integration_xkiro_active_slug();
                $model_id = $models[$slug]["model_id"] ?? "";
                if ($model_id === "") $model_id = $models["mistral_small_4"]["model_id"];
                $test = integration_xkiro_test($new_key, $model_id);
                if (!$test["ok"]) {
                    si_flash("error", "API Key baru belum disimpan: " . integration_xkiro_failure_message($test["failure"]) . " API Key lama tetap aktif.");
                    break;
                }
                $conn->begin_transaction();
                try {
                    integration_setting_set("xkiro.api_key", integration_encrypt($new_key, "xkiro.api_key"), true, $admin_id);
                    integration_setting_set("xkiro.api_key_meta", json_encode(["hint" => integration_secret_hint($new_key), "updated_at" => gmdate("Y-m-d H:i:s")]), false, $admin_id);
                    integration_setting_set("xkiro.model_check." . $slug, json_encode($test), false, $admin_id);
                    $conn->commit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    throw $e;
                }
                unset($new_key);
                si_flash("success", "API Key xKiro tervalidasi dan tersimpan terenkripsi.");
                break;

            /* ---------- xKiro: kembali ke API Key environment ---------- */
            case "xkiro_clear_key":
                if (integration_xkiro_env_key() === "") {
                    si_flash("error", "XKIRO_API_KEY di environment kosong. API Key tersimpan tidak dihapus agar chatbot tetap berjalan.");
                    break;
                }
                integration_setting_delete("xkiro.api_key");
                integration_setting_delete("xkiro.api_key_meta");
                si_flash("success", "Chatbot kembali memakai API Key dari environment (XKIRO_API_KEY).");
                break;

            /* ---------- xKiro: uji kompatibilitas model ---------- */
            case "xkiro_test_model":
                $slug = (string)($_POST["model_slug"] ?? "");
                if (!isset($models[$slug])) { si_flash("error", "Model tidak dikenal."); break; }
                $current = integration_xkiro_resolve();
                $test = integration_xkiro_test((string)$current["key"], (string)$models[$slug]["model_id"]);
                integration_setting_set("xkiro.model_check." . $slug, json_encode($test), false, $admin_id);
                if ($test["ok"]) {
                    si_flash("success", "Uji " . $models[$slug]["label"] . " berhasil (" . (int)$test["duration_ms"] . " ms). Model kompatibel dengan format chatbot existing.");
                } else {
                    si_flash("error", "Uji " . $models[$slug]["label"] . " gagal: " . integration_xkiro_failure_message($test["failure"]));
                }
                break;

            /* ---------- xKiro: pilih model aktif ---------- */
            case "xkiro_set_model":
                $slug = (string)($_POST["model_slug"] ?? "");
                if (!isset($models[$slug])) { si_flash("error", "Model tidak dikenal."); break; }
                $current = integration_xkiro_resolve();
                $test = integration_xkiro_test((string)$current["key"], (string)$models[$slug]["model_id"]);
                integration_setting_set("xkiro.model_check." . $slug, json_encode($test), false, $admin_id);
                if (!$test["ok"]) {
                    si_flash("error", $models[$slug]["label"] . " belum diaktifkan: " . integration_xkiro_failure_message($test["failure"]) . " Model sebelumnya tetap aktif.");
                    break;
                }
                integration_setting_set("xkiro.active_model", $slug, false, $admin_id);
                si_flash("success", "Model aktif: " . $models[$slug]["label"] . ". Prompt, konteks, dan endpoint per role tidak berubah.");
                break;

            /* ---------- Fonnte: tambah / perbarui device ---------- */
            case "fonnte_save_device":
                $device_id = (int)($_POST["device_id"] ?? 0);
                $device_name = trim(strip_tags((string)($_POST["device_name"] ?? "")));
                $sender = integration_fonnte_normalize_number((string)($_POST["sender_number"] ?? ""));
                $token = trim((string)($_POST["device_token"] ?? ""));

                if ($device_name === "" || (function_exists("mb_strlen") ? mb_strlen($device_name) : strlen($device_name)) > 100) { si_flash("error", "Nama device wajib diisi (maksimal 100 karakter)."); break; }
                if ($sender === null) { si_flash("error", "Format nomor pengirim tidak valid. Contoh: 6281234567890 atau 081234567890."); break; }

                $existing = $device_id > 0 ? integration_fonnte_device($device_id) : null;
                if ($device_id > 0 && !$existing) { si_flash("error", "Device tidak ditemukan."); break; }
                if (!$existing && count(integration_fonnte_devices()) >= INTEGRATION_FONNTE_MAX_DEVICES) {
                    si_flash("error", "Maksimal " . INTEGRATION_FONNTE_MAX_DEVICES . " profil device."); break;
                }
                if (!$existing && $token === "") { si_flash("error", "Device Token wajib diisi untuk device baru."); break; }

                $check_token = $token;
                if ($token !== "") {
                    if (!$encryption["ok"]) { si_flash("error", "Enkripsi belum siap (INTEGRATION_ENCRYPTION_KEY). Token tidak disimpan."); break; }
                    if (!integration_secret_is_well_formed($token, 8, 255)) { si_flash("error", "Format Device Token tidak valid."); break; }
                } elseif ($existing && $existing["sender_number"] !== $sender) {
                    $check_token = (string)integration_fonnte_device_token_from_row($existing);
                    if ($check_token === "") { si_flash("error", "Token tersimpan tidak dapat dibaca. Masukkan ulang Device Token."); break; }
                }

                $check = null;
                if ($check_token !== "") {
                    $check = integration_fonnte_check_token($check_token);
                    if (!$check["ok"]) {
                        si_flash("error", "Device belum disimpan: " . integration_fonnte_failure_message($check) . " Konfigurasi sebelumnya tetap dipakai.");
                        break;
                    }
                    if ($check["device"] !== null && $check["device"] !== "" && $check["device"] !== $sender) {
                        si_flash("error", "Device belum disimpan: " . integration_fonnte_failure_message($check, $sender));
                        break;
                    }
                }

                $conn->begin_transaction();
                try {
                    if ($existing) {
                        if ($token !== "") {
                            $cipher = integration_encrypt($token, "fonnte.device_token");
                            $hint = integration_secret_hint($token);
                            $stmt = $conn->prepare("UPDATE integration_fonnte_devices SET device_name = ?, sender_number = ?, token_ciphertext = ?, token_hint = ? WHERE id = ?");
                            $stmt->bind_param("ssssi", $device_name, $sender, $cipher, $hint, $device_id);
                        } else {
                            $stmt = $conn->prepare("UPDATE integration_fonnte_devices SET device_name = ?, sender_number = ? WHERE id = ?");
                            $stmt->bind_param("ssi", $device_name, $sender, $device_id);
                        }
                        $stmt->execute();
                        $saved_id = $device_id;
                    } else {
                        $cipher = integration_encrypt($token, "fonnte.device_token");
                        $hint = integration_secret_hint($token);
                        $stmt = $conn->prepare("INSERT INTO integration_fonnte_devices (device_name, sender_number, token_ciphertext, token_hint, is_enabled) VALUES (?, ?, ?, ?, 1)");
                        $stmt->bind_param("ssss", $device_name, $sender, $cipher, $hint);
                        $stmt->execute();
                        $saved_id = (int)$conn->insert_id;
                    }
                    if ($saved_id < 1) {
                        throw new RuntimeException("Fonnte device write returned no row ID.");
                    }
                    $verify_stmt = $conn->prepare("SELECT device_name, sender_number, token_ciphertext, token_hint FROM integration_fonnte_devices WHERE id = ? LIMIT 1 FOR UPDATE");
                    if (!$verify_stmt) {
                        throw new RuntimeException("Fonnte device write verification could not be prepared.");
                    }
                    $verify_stmt->bind_param("i", $saved_id);
                    if (!$verify_stmt->execute()) {
                        throw new RuntimeException("Fonnte device write verification failed.");
                    }
                    $saved_row = $verify_stmt->get_result()->fetch_assoc();
                    $saved_token = $saved_row ? integration_decrypt($saved_row["token_ciphertext"] ?? null, "fonnte.device_token") : null;
                    $token_matches = $saved_token !== null
                        && ($token === "" || hash_equals($token, $saved_token))
                        && hash_equals((string)($saved_row["token_hint"] ?? ""), integration_secret_hint($saved_token));
                    unset($saved_token);
                    if (!$saved_row
                        || $saved_row["device_name"] !== $device_name
                        || $saved_row["sender_number"] !== $sender
                        || !$token_matches) {
                        throw new RuntimeException("Fonnte device write verification did not match submitted values.");
                    }
                    if ($check !== null) integration_fonnte_store_check($saved_id, $check);
                    $conn->commit();
                } catch (mysqli_sql_exception $e) {
                    $conn->rollback();
                    if ((int)$e->getCode() === 1062) { si_flash("error", "Nomor pengirim sudah terdaftar pada profil lain."); break; }
                    throw $e;
                }
                unset($token, $check_token);
                $suffix = ($check !== null && $check["status"] !== "connect") ? " Catatan: device sedang tidak terhubung, belum bisa dijadikan pengirim aktif." : "";
                si_flash("success", "Profil device \"" . $device_name . "\" tersimpan." . $suffix);
                break;

            /* ---------- Fonnte: cek koneksi device ---------- */
            case "fonnte_check_device":
                $device_id = (int)($_POST["device_id"] ?? 0);
                $row = integration_fonnte_device($device_id);
                if (!$row) { si_flash("error", "Device tidak ditemukan."); break; }
                $token = integration_fonnte_device_token_from_row($row);
                if ($token === null) { si_flash("error", "Token tersimpan tidak dapat dibaca (kunci enkripsi berubah?). Masukkan ulang Device Token."); break; }
                $check = integration_fonnte_check_token($token);
                unset($token);
                if ($check["ok"] || $check["status"] === "invalid") integration_fonnte_store_check($device_id, $check);
                if ($check["ok"] && $check["status"] === "connect") {
                    si_flash("success", "Device \"" . $row["device_name"] . "\" terhubung (" . (string)$check["device"] . ").");
                } else {
                    si_flash("error", "Device \"" . $row["device_name"] . "\": " . integration_fonnte_failure_message($check));
                }
                break;

            /* ---------- Fonnte: pilih device aktif ---------- */
            case "fonnte_set_active":
                $device_id = (int)($_POST["device_id"] ?? 0);
                $row = integration_fonnte_device($device_id);
                if (!$row) { si_flash("error", "Device tidak ditemukan."); break; }
                if ((int)$row["is_enabled"] !== 1) { si_flash("error", "Device nonaktif. Aktifkan profil terlebih dahulu."); break; }
                $token = integration_fonnte_device_token_from_row($row);
                if ($token === null) { si_flash("error", "Token tersimpan tidak dapat dibaca. Device aktif tidak diubah."); break; }
                $check = integration_fonnte_check_token($token);
                unset($token);
                if ($check["ok"] || $check["status"] === "invalid") integration_fonnte_store_check($device_id, $check);
                $number_ok = $check["device"] === null || $check["device"] === "" || $check["device"] === $row["sender_number"];
                if (!$check["ok"] || $check["status"] !== "connect" || !$number_ok) {
                    si_flash("error", "Device aktif tidak diubah: " . integration_fonnte_failure_message($check, (string)$row["sender_number"]));
                    break;
                }
                integration_setting_set("fonnte.active_device_id", (string)$device_id, false, $admin_id);
                si_flash("success", "Pengirim OTP aktif: " . $row["device_name"] . " (" . $row["sender_number"] . "). Nomor tujuan Customer tidak berubah.");
                break;

            /* ---------- Fonnte: aktif/nonaktifkan profil ---------- */
            case "fonnte_toggle_device":
                $device_id = (int)($_POST["device_id"] ?? 0);
                $row = integration_fonnte_device($device_id);
                if (!$row) { si_flash("error", "Device tidak ditemukan."); break; }
                $new_state = (int)$row["is_enabled"] === 1 ? 0 : 1;
                if ($new_state === 0 && integration_fonnte_active_id() === $device_id) {
                    si_flash("error", "Device ini sedang menjadi pengirim aktif. Pilih pengirim lain terlebih dahulu.");
                    break;
                }
                $stmt = $conn->prepare("UPDATE integration_fonnte_devices SET is_enabled = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_state, $device_id);
                $stmt->execute();
                si_flash("success", "Profil \"" . $row["device_name"] . "\" " . ($new_state ? "diaktifkan." : "dinonaktifkan."));
                break;

            /* ---------- Fonnte: kembali ke token environment ---------- */
            case "fonnte_use_env":
                if (integration_fonnte_env_token() === "") {
                    si_flash("error", "FONNTE_API_KEY di environment kosong. Device aktif tidak diubah.");
                    break;
                }
                integration_setting_delete("fonnte.active_device_id");
                si_flash("success", "Pengirim OTP kembali memakai token environment (FONNTE_API_KEY).");
                break;

            /* ---------- Fonnte: cek token environment ---------- */
            case "fonnte_check_env":
                $env_token = integration_fonnte_env_token();
                if ($env_token === "") { si_flash("error", "FONNTE_API_KEY di environment kosong."); break; }
                $check = integration_fonnte_check_token($env_token);
                unset($env_token);
                if ($check["ok"]) {
                    integration_setting_set("fonnte.env_check", json_encode(["status" => $check["status"], "device" => $check["device"], "package" => $check["package"], "at" => gmdate("Y-m-d H:i:s")]), false, $admin_id);
                    si_flash($check["status"] === "connect" ? "success" : "error", "Token environment: device " . (string)$check["device"] . " — " . $check["status"] . ".");
                } else {
                    si_flash("error", "Token environment: " . integration_fonnte_failure_message($check));
                }
                break;

            default:
                si_flash("error", "Aksi tidak dikenal.");
        }
    } catch (Throwable $e) {
        error_log("Admin integration settings action failed: " . preg_replace('/[^a-z_]/', '', $action));
        si_flash("error", "Perubahan belum berhasil disimpan. Konfigurasi sebelumnya tetap dipakai.");
    }
    header("Location: settings_integrasi.php");
    exit;
}

/* ------------------------------------------------------------------ */
/* Data untuk tampilan (tanpa rahasia)                                 */
/* ------------------------------------------------------------------ */
$tables_ready = integration_tables_ready();
$encryption = integration_encryption_status();
$models = integration_xkiro_models();
$active_slug = $tables_ready ? integration_xkiro_active_slug() : "mistral_small_4";
$key_status = integration_xkiro_key_status();
$model_checks = [];
foreach (array_keys($models) as $slug) {
    $model_checks[$slug] = $tables_ready ? integration_setting_get_json("xkiro.model_check." . $slug) : null;
}
$devices = integration_fonnte_devices();
$active_device_id = $tables_ready ? integration_fonnte_active_id() : null;
$env_fonnte_token = integration_fonnte_env_token();
$env_fonnte_hint = $env_fonnte_token !== "" ? integration_secret_hint($env_fonnte_token) : "";
unset($env_fonnte_token);
$env_check = $tables_ready ? integration_setting_get_json("fonnte.env_check") : null;
$active_device_valid = false;
foreach ($devices as $d) {
    if ((int)$d["id"] === $active_device_id && (int)$d["is_enabled"] === 1 && (int)$d["has_token"] === 1) $active_device_valid = true;
}
$edit_id = (int)($_GET["edit"] ?? 0);
$edit_device = null;
foreach ($devices as $d) if ((int)$d["id"] === $edit_id) $edit_device = $d;

$encryption_messages = [
    "missing_key" => "Environment INTEGRATION_ENCRYPTION_KEY belum diset. Penyimpanan API Key/Token dinonaktifkan.",
    "invalid_key" => "INTEGRATION_ENCRYPTION_KEY tidak valid. Harus 32 byte (base64 44 karakter atau hex 64 karakter).",
    "openssl_unavailable" => "Ekstensi OpenSSL dengan AES-256-GCM tidak tersedia di PHP.",
];
$input_class = "w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl px-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none";
$btn_primary = "bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-4 py-2.5 rounded-xl text-sm transition-all shadow-md shadow-amber-950/40 flex items-center justify-center gap-1.5 whitespace-nowrap";
$btn_ghost = "bg-stone-800/60 hover:bg-stone-700/60 border border-stone-700 text-stone-200 font-semibold px-3 py-2 rounded-lg text-xs transition-all flex items-center justify-center gap-1.5 whitespace-nowrap";

function si_badge(string $tone, string $label): string
{
    $tones = [
        "ok" => "bg-emerald-500/10 text-emerald-400 border-emerald-500/30",
        "warn" => "bg-amber-500/10 text-amber-400 border-amber-500/30",
        "bad" => "bg-rose-500/10 text-rose-400 border-rose-500/30",
        "muted" => "bg-stone-700/30 text-stone-400 border-stone-700",
    ];
    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider ' . ($tones[$tone] ?? $tones["muted"]) . '">' . si_h($label) . '</span>';
}

include "header.php";
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">Konfigurasi Integrasi</h1>
        <p class="text-xs text-stone-400 mt-1">Kelola API Key &amp; model chatbot xKiro serta device pengirim WhatsApp Fonnte. Rahasia disimpan terenkripsi dan tidak pernah ditampilkan ulang.</p>
    </div>
</div>

<?php if (!$tables_ready): ?>
<div class="mb-6 p-4 rounded-2xl border border-rose-500/30 bg-rose-500/10 text-rose-300 text-sm">
    Tabel konfigurasi belum tersedia. Terapkan <code class="font-mono">database/migrations/20261109_add_integration_settings.sql</code>. Sampai saat itu, aplikasi tetap memakai konfigurasi environment.
</div>
<?php endif; ?>
<?php if (!$encryption["ok"]): ?>
<div class="mb-6 p-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 text-amber-300 text-sm">
    <?= si_h($encryption_messages[$encryption["reason"]] ?? "Enkripsi belum siap.") ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

<!-- ========================= A. CHATBOT AI ========================= -->
<section class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl shadow-xl overflow-hidden">
    <div class="p-4 border-b border-stone-800/80 flex items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <i data-lucide="bot" class="w-4 h-4 text-amber-400"></i>
            <h2 class="text-xs font-bold text-white uppercase tracking-wider">A. Chatbot AI</h2>
        </div>
        <span class="text-xs text-stone-500">Provider: <strong class="text-stone-300">xKiro</strong></span>
    </div>

    <div class="p-5 space-y-6">
        <!-- Status API Key -->
        <div>
            <h3 class="text-sm font-bold text-white mb-2">API Key</h3>
            <div class="flex flex-wrap items-center gap-2 text-xs text-stone-400">
                <?php if ($key_status["configured"]): ?>
                    <?= si_badge("ok", "Sudah dikonfigurasi") ?>
                    <span class="font-mono text-stone-300"><?= si_h(integration_mask_hint($key_status["hint"])) ?></span>
                    <span>Sumber: <strong class="text-stone-300"><?= $key_status["source"] === "database" ? "Pengaturan Admin (terenkripsi)" : "Environment XKIRO_API_KEY" ?></strong></span>
                    <?php if ($key_status["updated_at"] !== ""): ?><span>· diperbarui <?= si_h($key_status["updated_at"]) ?> UTC</span><?php endif; ?>
                <?php else: ?>
                    <?= si_badge("bad", "Belum dikonfigurasi") ?>
                <?php endif; ?>
            </div>
            <?php if ($key_status["db_unreadable"]): ?>
                <p class="mt-2 text-xs text-amber-400">API Key tersimpan tidak dapat didekripsi (kunci enkripsi berubah?). Sementara memakai environment. Simpan ulang API Key.</p>
            <?php endif; ?>

            <form method="POST" data-loading class="mt-3 flex flex-col sm:flex-row gap-2" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="xkiro_save_key">
                <label class="sr-only" for="api_key">API Key xKiro baru</label>
                <input id="api_key" type="password" name="api_key" required minlength="16" maxlength="512" autocomplete="new-password" spellcheck="false" placeholder="Tempel API Key xKiro baru" class="<?= $input_class ?>" <?= ($tables_ready && $encryption["ok"]) ? "" : "disabled" ?>>
                <button type="submit" class="<?= $btn_primary ?>" <?= ($tables_ready && $encryption["ok"]) ? "" : "disabled" ?>>
                    <i data-lucide="key-round" class="w-4 h-4"></i> <?= $key_status["source"] === "database" ? "Perbarui" : "Simpan" ?> API Key
                </button>
            </form>
            <p class="mt-2 text-[11px] text-stone-500">API Key baru diuji ke xKiro memakai model aktif. Jika gagal, key lama tetap dipakai.</p>

            <?php if ($key_status["source"] === "database" && $key_status["env_available"]): ?>
            <form method="POST" data-loading data-confirm data-confirm-title="Kembali ke API Key environment" data-confirm-message="API Key yang tersimpan di pengaturan Admin akan dihapus dan chatbot kembali memakai XKIRO_API_KEY. Lanjutkan?" data-confirm-label="Kembalikan" data-confirm-danger="true" class="mt-2">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="xkiro_clear_key">
                <button type="submit" class="<?= $btn_ghost ?>"><i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Pakai API Key environment</button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Pilihan model -->
        <div>
            <h3 class="text-sm font-bold text-white mb-2">Model Aktif</h3>
            <div class="space-y-3">
                <?php foreach ($models as $slug => $m):
                    $check = $model_checks[$slug];
                    $is_active = $slug === $active_slug;
                ?>
                <div class="p-4 rounded-xl border <?= $is_active ? "border-amber-500/40 bg-amber-500/5" : "border-stone-800 bg-[#131926]/60" ?>">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-white text-sm"><?= si_h($m["label"]) ?></span>
                            <?= $is_active ? si_badge("ok", "Aktif") : "" ?>
                            <?php if ($check): ?>
                                <?= $check["ok"] ? si_badge("ok", "Kompatibel") : si_badge("bad", "Uji gagal") ?>
                            <?php else: ?>
                                <?= si_badge("muted", $m["is_default"] ? "Model utama" : "Belum diuji") ?>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" data-loading>
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="xkiro_test_model">
                                <input type="hidden" name="model_slug" value="<?= si_h($slug) ?>">
                                <button type="submit" class="<?= $btn_ghost ?>" <?= ($tables_ready && $key_status["configured"] && $m["model_id"] !== "") ? "" : "disabled" ?>><i data-lucide="flask-conical" class="w-3.5 h-3.5"></i> Uji</button>
                            </form>
                            <?php if (!$is_active): ?>
                            <form method="POST" data-loading data-confirm data-confirm-title="Ganti model chatbot" data-confirm-message="Chatbot kelima role akan memakai model {object}. Model diuji ulang sebelum diaktifkan. Lanjutkan?" data-confirm-object="<?= si_h($m["label"]) ?>" data-confirm-label="Aktifkan">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="xkiro_set_model">
                                <input type="hidden" name="model_slug" value="<?= si_h($slug) ?>">
                                <button type="submit" class="<?= $btn_primary ?> !py-2 !text-xs" <?= ($tables_ready && $m["model_id"] !== "") ? "" : "disabled" ?>><i data-lucide="check" class="w-3.5 h-3.5"></i> Aktifkan</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-[11px] text-stone-400">
                        <div>Model ID: <span class="font-mono text-stone-300 break-all"><?= $m["model_id"] !== "" ? si_h($m["model_id"]) : "— (Model ID tidak tersedia)" ?></span></div>
                        <div>Context: <span class="text-stone-300"><?= si_h($check["catalog_context"] ?? null ? number_format((int)$check["catalog_context"]) . " token" : ($m["context"] ?? "tidak tersedia")) ?></span></div>
                        <?php if ($check): ?>
                        <div>Uji terakhir: <span class="text-stone-300"><?= si_h($check["checked_at"] ?? "") ?> UTC</span></div>
                        <div>Waktu respons: <span class="text-stone-300"><?= isset($check["duration_ms"]) ? (int)$check["duration_ms"] . " ms" : "—" ?></span><?= !$check["ok"] ? ' · <span class="text-rose-400">' . si_h(integration_xkiro_failure_message((string)$check["failure"])) . "</span>" : "" ?></div>
                        <?php endif; ?>
                    </dl>
                    <p class="mt-1 text-[11px] text-stone-500"><?= si_h($m["note"]) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-3 text-[11px] text-stone-500">Pergantian model hanya mengubah Model ID xKiro. System prompt, persona, konteks role, riwayat percakapan, endpoint, dan fallback provider (NVIDIA → Gemini → DeepSeek) tidak berubah.</p>
        </div>
    </div>
</section>

<!-- ======================= B. WHATSAPP GATEWAY ====================== -->
<section class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl shadow-xl overflow-hidden">
    <div class="p-4 border-b border-stone-800/80 flex items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <i data-lucide="message-circle" class="w-4 h-4 text-amber-400"></i>
            <h2 class="text-xs font-bold text-white uppercase tracking-wider">B. WhatsApp Gateway</h2>
        </div>
        <span class="text-xs text-stone-500">Provider: <strong class="text-stone-300">Fonnte</strong></span>
    </div>

    <div class="p-5 space-y-6">
        <div>
            <h3 class="text-sm font-bold text-white mb-2">Device Pengirim</h3>
            <div class="space-y-3">
                <!-- Default environment -->
                <div class="p-4 rounded-xl border <?= !$active_device_valid ? "border-amber-500/40 bg-amber-500/5" : "border-stone-800 bg-[#131926]/60" ?>">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-white text-sm">Default (environment)</span>
                            <?= !$active_device_valid ? si_badge("ok", "Pengirim aktif") : "" ?>
                            <?= $env_fonnte_hint !== "" ? si_badge("ok", "Token tersedia") : si_badge("bad", "Token kosong") ?>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" data-loading>
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="fonnte_check_env">
                                <button type="submit" class="<?= $btn_ghost ?>" <?= ($tables_ready && $env_fonnte_hint !== "") ? "" : "disabled" ?>><i data-lucide="activity" class="w-3.5 h-3.5"></i> Cek koneksi</button>
                            </form>
                            <?php if ($active_device_valid): ?>
                            <form method="POST" data-loading data-confirm data-confirm-title="Pakai token environment" data-confirm-message="OTP berikutnya akan dikirim memakai FONNTE_API_KEY. Lanjutkan?" data-confirm-label="Gunakan">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="fonnte_use_env">
                                <button type="submit" class="<?= $btn_primary ?> !py-2 !text-xs" <?= $env_fonnte_hint !== "" ? "" : "disabled" ?>><i data-lucide="check" class="w-3.5 h-3.5"></i> Jadikan aktif</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-stone-400">
                        Token: <span class="font-mono text-stone-300"><?= $env_fonnte_hint !== "" ? si_h(integration_mask_hint($env_fonnte_hint)) : "—" ?></span>
                        <?php if ($env_check): ?> · Device: <span class="font-mono text-stone-300"><?= si_h($env_check["device"] ?? "—") ?></span> · Status: <span class="text-stone-300"><?= si_h($env_check["status"] ?? "—") ?></span> (<?= si_h($env_check["at"] ?? "") ?> UTC)<?php endif; ?>
                    </div>
                </div>

                <?php foreach ($devices as $d):
                    $is_active = $active_device_valid && (int)$d["id"] === $active_device_id;
                    $conn_status = (string)($d["last_check_status"] ?? "");
                ?>
                <div class="p-4 rounded-xl border <?= $is_active ? "border-amber-500/40 bg-amber-500/5" : "border-stone-800 bg-[#131926]/60" ?>">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-white text-sm"><?= si_h($d["device_name"]) ?></span>
                            <?= $is_active ? si_badge("ok", "Pengirim aktif") : "" ?>
                            <?= (int)$d["is_enabled"] === 1 ? "" : si_badge("muted", "Nonaktif") ?>
                            <?= (int)$d["has_token"] === 1 ? si_badge("ok", "Terkonfigurasi") : si_badge("bad", "Token kosong") ?>
                            <?= match ($conn_status) {
                                "connect" => si_badge("ok", "Connect"),
                                "disconnect" => si_badge("warn", "Disconnect"),
                                "invalid" => si_badge("bad", "Token invalid"),
                                "" => si_badge("muted", "Belum dicek"),
                                default => si_badge("muted", $conn_status),
                            } ?>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" data-loading>
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="fonnte_check_device">
                                <input type="hidden" name="device_id" value="<?= (int)$d["id"] ?>">
                                <button type="submit" class="<?= $btn_ghost ?>"><i data-lucide="activity" class="w-3.5 h-3.5"></i> Cek</button>
                            </form>
                            <a href="?edit=<?= (int)$d["id"] ?>#form-device" class="<?= $btn_ghost ?>"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Ubah</a>
                            <?php if (!$is_active): ?>
                            <form method="POST" data-loading>
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="fonnte_toggle_device">
                                <input type="hidden" name="device_id" value="<?= (int)$d["id"] ?>">
                                <button type="submit" class="<?= $btn_ghost ?>"><i data-lucide="power" class="w-3.5 h-3.5"></i> <?= (int)$d["is_enabled"] === 1 ? "Nonaktifkan" : "Aktifkan" ?></button>
                            </form>
                            <form method="POST" data-loading data-confirm data-confirm-title="Ganti pengirim OTP" data-confirm-message="OTP berikutnya akan dikirim dari {object}. Koneksi device dicek ulang sebelum diganti. Lanjutkan?" data-confirm-object="<?= si_h($d["device_name"] . " (" . $d["sender_number"] . ")") ?>" data-confirm-label="Jadikan aktif">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="fonnte_set_active">
                                <input type="hidden" name="device_id" value="<?= (int)$d["id"] ?>">
                                <button type="submit" class="<?= $btn_primary ?> !py-2 !text-xs" <?= ((int)$d["is_enabled"] === 1 && (int)$d["has_token"] === 1) ? "" : "disabled" ?>><i data-lucide="check" class="w-3.5 h-3.5"></i> Jadikan aktif</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-[11px] text-stone-400">
                        <div>Nomor pengirim: <span class="font-mono text-stone-300"><?= si_h($d["sender_number"]) ?></span></div>
                        <div>Token: <span class="font-mono text-stone-300"><?= si_h(integration_mask_hint($d["token_hint"])) ?></span></div>
                        <?php if (!empty($d["last_check_at"])): ?>
                        <div>Device di Fonnte: <span class="font-mono text-stone-300"><?= si_h($d["last_check_device"] ?? "—") ?></span></div>
                        <div>Paket: <span class="text-stone-300"><?= si_h($d["last_check_package"] ?? "—") ?></span><?= !empty($d["last_check_quota"]) ? " · Kuota: <span class=\"text-stone-300\">" . si_h($d["last_check_quota"]) . "</span>" : "" ?><?= !empty($d["last_check_expired"]) ? " · Exp: <span class=\"text-stone-300\">" . si_h($d["last_check_expired"]) . "</span>" : "" ?></div>
                        <div class="sm:col-span-2">Dicek: <span class="text-stone-300"><?= si_h($d["last_check_at"]) ?> UTC</span></div>
                        <?php endif; ?>
                    </dl>
                </div>
                <?php endforeach; ?>
                <?php if (!$devices): ?>
                    <p class="text-xs text-stone-500">Belum ada profil device. Tambahkan Device 1 (nomor yang sudah dipakai) dan Device 2 di bawah.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form tambah / perbarui device -->
        <div id="form-device">
            <h3 class="text-sm font-bold text-white mb-2"><?= $edit_device ? "Perbarui Device: " . si_h($edit_device["device_name"]) : "Tambah Device" ?></h3>
            <form method="POST" data-loading class="space-y-3" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="fonnte_save_device">
                <input type="hidden" name="device_id" value="<?= $edit_device ? (int)$edit_device["id"] : 0 ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="device_name" class="block text-[11px] text-stone-400 mb-1">Nama device</label>
                        <input id="device_name" type="text" name="device_name" required maxlength="100" placeholder="Device 1" value="<?= si_h($edit_device["device_name"] ?? "") ?>" class="<?= $input_class ?>">
                    </div>
                    <div>
                        <label for="sender_number" class="block text-[11px] text-stone-400 mb-1">Nomor pengirim (terdaftar di Fonnte)</label>
                        <input id="sender_number" type="tel" name="sender_number" required maxlength="20" placeholder="6281234567890" value="<?= si_h($edit_device["sender_number"] ?? "") ?>" class="<?= $input_class ?>">
                    </div>
                </div>
                <div>
                    <label for="device_token" class="block text-[11px] text-stone-400 mb-1">Device Token <?= $edit_device ? "(kosongkan jika tidak diganti)" : "" ?></label>
                    <input id="device_token" type="password" name="device_token" <?= $edit_device ? "" : "required" ?> maxlength="255" autocomplete="new-password" spellcheck="false" placeholder="Token dari menu Device di dashboard Fonnte" class="<?= $input_class ?>" <?= $encryption["ok"] ? "" : "disabled" ?>>
                    <p class="mt-1 text-[11px] text-stone-500">Gunakan <strong>Device Token</strong> (per device), bukan Account Token. Token dicek ke Fonnte tanpa mengirim pesan WhatsApp.</p>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="<?= $btn_primary ?>" <?= ($tables_ready && ($encryption["ok"] || $edit_device)) ? "" : "disabled" ?>><i data-lucide="save" class="w-4 h-4"></i> Simpan Device</button>
                    <?php if ($edit_device): ?><a href="settings_integrasi.php#form-device" class="<?= $btn_ghost ?>">Batal</a><?php endif; ?>
                </div>
            </form>
        </div>
        <p class="text-[11px] text-stone-500">Pairing/scan QR dilakukan di dashboard Fonnte. Hanya satu device yang menjadi pengirim OTP aktif; nomor tujuan tetap nomor WhatsApp Customer.</p>
    </div>
</section>

</div>

<?php include "footer.php"; ?>
