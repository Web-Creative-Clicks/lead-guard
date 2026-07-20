<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . LEADGUARD_SAID_TABLE;

/* --- Handle row actions ----------------------------------------------- */
if (isset($_POST['leadguard_said_action'], $_POST['leadguard_said_id']) && check_admin_referer('leadguard_said_row_action')) {
    $id = (int) $_POST['leadguard_said_id'];
    $action = sanitize_text_field($_POST['leadguard_said_action']);

    if ($action === 'mark_fake') {
        $row = $wpdb->get_row($wpdb->prepare("SELECT ip_address, email FROM {$table} WHERE id=%d", $id));
        $wpdb->update($table, ['status' => 'rejected'], ['id' => $id]);
        if ($row) {
            $settings = leadguard_said_get_settings();

            // Add IP (deduplicated, validated)
            $ips = array_unique(array_filter(
                array_map('trim', explode("\n", $settings['blocked_ips'])),
                fn($v) => (bool) filter_var($v, FILTER_VALIDATE_IP)
            ));
            if (!empty($row->ip_address) && !in_array($row->ip_address, $ips, true)) {
                $ips[] = $row->ip_address;
            }
            $settings['blocked_ips'] = implode("\n", $ips);

            // Add email (deduplicated)
            $emails = array_filter(array_map('trim', explode("\n", $settings['blocked_emails'])));
            if (!empty($row->email) && !in_array(strtolower($row->email), array_map('strtolower', $emails), true)) {
                $emails[] = strtolower($row->email);
            }
            $settings['blocked_emails'] = implode("\n", $emails);

            update_option('leadguard_said_settings', $settings);
        }
        echo '<div class="updated"><p>Marked fake — IP &amp; email added to blocklists.</p></div>';

    } elseif ($action === 'mark_clean') {
        $wpdb->update($table, ['status' => 'clean'], ['id' => $id]);
        echo '<div class="updated"><p>Marked clean.</p></div>';

    } elseif ($action === 'delete') {
        $wpdb->delete($table, ['id' => $id], ['%d']);
        echo '<div class="updated"><p>Submission deleted.</p></div>';

    } elseif ($action === 'delete_list') {
        $filter = sanitize_text_field($_POST['delete_list_status'] ?? '');
        if ($filter) {
            $wpdb->delete($table, ['status' => $filter], ['%s']);
            echo '<div class="updated"><p>All <strong>' . esc_html(strtoupper($filter)) . '</strong> submissions deleted.</p></div>';
        } else {
            $wpdb->query("TRUNCATE TABLE {$table}");
            echo '<div class="updated"><p>All submissions deleted.</p></div>';
        }
    }
}

$filter = sanitize_text_field($_GET['status'] ?? '');
$where = $filter ? $wpdb->prepare('WHERE status = %s', $filter) : '';
$rows = $wpdb->get_results("SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT 200");
$counts = $wpdb->get_results("SELECT status, COUNT(*) c FROM {$table} GROUP BY status", OBJECT_K);
?>
<div class="wrap">
    <h1>Lead Guard — Submissions</h1>
    <ul class="subsubsub">
        <li><a href="?page=leadguard-said">All</a> |</li>
        <li><a href="?page=leadguard-said&status=clean">Clean (
                <?php echo (int) ($counts['clean']->c ?? 0); ?>)
            </a> |
        </li>
        <li><a href="?page=leadguard-said&status=suspicious">Suspicious
                (
                <?php echo (int) ($counts['suspicious']->c ?? 0); ?>)
            </a> |</li>
        <li><a href="?page=leadguard-said&status=rejected">Rejected
                (
                <?php echo (int) ($counts['rejected']->c ?? 0); ?>)
            </a></li>
    </ul>

    <!-- Bulk delete -->
    <form method="post" style="margin:12px 0;"
        onsubmit="return confirm('Delete <?php echo $filter ? esc_js(strtoupper($filter)) . ' ' : 'ALL '; ?>submissions? This cannot be undone.');">
        <?php wp_nonce_field('leadguard_said_row_action'); ?>
        <input type="hidden" name="leadguard_said_id" value="0">
        <input type="hidden" name="leadguard_said_action" value="delete_list">
        <input type="hidden" name="delete_list_status" value="<?php echo esc_attr($filter); ?>">
        <button class="button button-secondary" style="color:#d63638;border-color:#d63638;">
            🗑 Delete
            <?php echo $filter ? esc_html(strtoupper($filter)) . ' list' : 'ALL submissions'; ?>
        </button>
    </form>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Source</th>
                <th>IP</th>
                <th>Device</th>
                <th>Score</th>
                <th>Status</th>
                <th>Reasons</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                // Source badge config
                $src_labels = [
                    'meta_ads' => ['📘 Meta Ads', '#1877f2'],
                    'google_ads' => ['🔍 Google Ads', '#34a853'],
                    'tiktok_ads' => ['🎵 TikTok Ads', '#010101'],
                    'bing_ads' => ['🅱 Bing Ads', '#0078d4'],
                    'twitter_ads' => ['𝕏 Twitter Ads', '#1da1f2'],
                    'linkedin_ads' => ['in LinkedIn Ads', '#0a66c2'],
                    'snapchat_ads' => ['👻 Snapchat Ads', '#fffc00'],
                    'paid_utm' => ['💰 Paid (UTM)', '#e67e22'],
                    'utm_organic' => ['🔗 UTM Organic', '#8e44ad'],
                    'organic_search' => ['🔎 Organic Search', '#27ae60'],
                    'organic_social' => ['📣 Organic Social', '#2980b9'],
                    'referral' => ['↩ Referral', '#7f8c8d'],
                    'direct' => ['🔗 Direct', '#95a5a6'],
                ];
                ?>
                <tr>
                    <td>
                        <?php echo esc_html($r->created_at); ?>
                    </td>
                    <td>
                        <?php echo esc_html($r->name); ?>
                    </td>
                    <td>
                        <?php echo esc_html($r->email); ?>
                    </td>
                    <td>
                        <?php echo esc_html($r->phone); ?>
                    </td>
                    <td>
                        <?php
                        $src = $r->traffic_source ?? 'direct';
                        [$src_label, $src_color] = $src_labels[$src] ?? [$src, '#999'];
                        $text_color = ($src === 'snapchat_ads') ? '#000' : '#fff';
                        echo '<span style="background:' . esc_attr($src_color) . ';color:' . esc_attr($text_color) . ';padding:2px 7px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;">' . esc_html($src_label) . '</span>';
                        if (!empty($r->utm_params)) {
                            $utms = json_decode($r->utm_params, true);
                            if (is_array($utms) && !empty($utms)) {
                                $utms_str = implode(' | ', array_map(fn($k, $v) => esc_html($k) . '=' . esc_html($v), array_keys($utms), $utms));
                                echo '<br><span style="font-size:10px;color:#666;">' . $utms_str . '</span>';
                            }
                        }
                        ?>
                    </td>
                    <td>
                        <?php echo esc_html($r->ip_address); ?>
                    </td>
                    <td style="font-size:11px;">
                        <?php
                        $dev_key = $r->device_id ?: $r->device_fingerprint;
                        if ($dev_key) {
                            $dev_short = substr($dev_key, 0, 8) . '…';
                            $dev_bad_count = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$table} WHERE (device_id=%s OR device_fingerprint=%s) AND status IN ('rejected','suspicious')",
                                $dev_key,
                                $dev_key
                            ));
                            echo '<code title="' . esc_attr($dev_key) . '">' . esc_html($dev_short) . '</code>';
                            if ($dev_bad_count > 1) {
                                echo ' <span style="color:#d63638;font-weight:700;">×' . (int) $dev_bad_count . '</span>';
                            }
                        } else {
                            echo '<span style="color:#d63638;">no cookie</span>';
                        }
                        ?>
                    </td>
                    <td><strong>
                            <?php echo (int) $r->fraud_score; ?>
                        </strong></td>
                    <td>
                        <?php
                        $color = match ($r->status) { 'clean' => '#2271b1', 'suspicious' => '#dba617', default => '#d63638'};
                        echo '<span style="color:' . esc_attr($color) . ';font-weight:600;">' . esc_html(strtoupper($r->status)) . '</span>';
                        ?>
                    </td>
                    <td style="max-width:220px;font-size:11px;color:#666;">
                        <?php echo esc_html($r->reasons); ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline;white-space:nowrap;">
                            <?php wp_nonce_field('leadguard_said_row_action'); ?>
                            <input type="hidden" name="leadguard_said_id" value="<?php echo (int) $r->id; ?>">
                            <button class="button button-small" name="leadguard_said_action" value="mark_fake">Block
                                IP+Email</button>
                            <button class="button button-small" name="leadguard_said_action" value="mark_clean">Mark
                                Clean</button>
                            <button class="button button-small" name="leadguard_said_action" value="delete"
                                style="color:#d63638;border-color:#d63638;"
                                onclick="return confirm('Delete this submission?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
