<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$leadguard_wcc_table = $wpdb->prefix . leadguard_wcc_TABLE;

/* --- Handle row actions ----------------------------------------------- */
if (isset($_POST['leadguard_wcc_action'], $_POST['leadguard_wcc_id']) && check_admin_referer('leadguard_wcc_row_action')) {
    $leadguard_wcc_id = absint($_POST['leadguard_wcc_id']);
    $leadguard_wcc_action = sanitize_key(wp_unslash($_POST['leadguard_wcc_action']));

    if ($leadguard_wcc_action === 'mark_fake') {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $leadguard_wcc_row = $wpdb->get_row($wpdb->prepare("SELECT ip_address, email FROM {$leadguard_wcc_table} WHERE id=%d", $leadguard_wcc_id));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($leadguard_wcc_table, ['status' => 'rejected'], ['id' => $leadguard_wcc_id]);
        if ($leadguard_wcc_row) {
            $leadguard_wcc_settings = leadguard_wcc_get_settings();

            // Add IP (deduplicated, validated)
            $leadguard_wcc_ips = array_unique(array_filter(
                array_map('trim', explode("\n", $leadguard_wcc_settings['blocked_ips'])),
                fn($v) => (bool) filter_var($v, FILTER_VALIDATE_IP)
            ));
            if (!empty($leadguard_wcc_row->ip_address) && !in_array($leadguard_wcc_row->ip_address, $leadguard_wcc_ips, true)) {
                $leadguard_wcc_ips[] = $leadguard_wcc_row->ip_address;
            }
            $leadguard_wcc_settings['blocked_ips'] = implode("\n", $leadguard_wcc_ips);

            // Add email (deduplicated)
            $leadguard_wcc_emails = array_filter(array_map('trim', explode("\n", $leadguard_wcc_settings['blocked_emails'])));
            if (!empty($leadguard_wcc_row->email) && !in_array(strtolower($leadguard_wcc_row->email), array_map('strtolower', $leadguard_wcc_emails), true)) {
                $leadguard_wcc_emails[] = strtolower($leadguard_wcc_row->email);
            }
            $leadguard_wcc_settings['blocked_emails'] = implode("\n", $leadguard_wcc_emails);

            update_option('leadguard_wcc_settings', $leadguard_wcc_settings);
        }
        echo '<div class="updated"><p>Marked fake â€” IP &amp; email added to blocklists.</p></div>';

    } elseif ($leadguard_wcc_action === 'mark_clean') {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($leadguard_wcc_table, ['status' => 'clean'], ['id' => $leadguard_wcc_id]);
        echo '<div class="updated"><p>Marked clean.</p></div>';

    } elseif ($leadguard_wcc_action === 'delete') {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($leadguard_wcc_table, ['id' => $leadguard_wcc_id], ['%d']);
        echo '<div class="updated"><p>Submission deleted.</p></div>';

    } elseif ($leadguard_wcc_action === 'delete_list') {
        $leadguard_wcc_del_filter = isset($_POST['delete_list_status']) ? sanitize_key(wp_unslash($_POST['delete_list_status'])) : '';
        if ($leadguard_wcc_del_filter && in_array($leadguard_wcc_del_filter, ['clean', 'suspicious', 'rejected'], true)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($leadguard_wcc_table, ['status' => $leadguard_wcc_del_filter], ['%s']);
            echo '<div class="updated"><p>All <strong>' . esc_html(strtoupper($leadguard_wcc_del_filter)) . '</strong> submissions deleted.</p></div>';
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("TRUNCATE TABLE {$leadguard_wcc_table}");
            echo '<div class="updated"><p>All submissions deleted.</p></div>';
        }
    }
}

$leadguard_wcc_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
if (in_array($leadguard_wcc_filter, ['clean', 'suspicious', 'rejected'], true)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $leadguard_wcc_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$leadguard_wcc_table} WHERE status = %s ORDER BY created_at DESC LIMIT 200", $leadguard_wcc_filter));
} else {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $leadguard_wcc_rows = $wpdb->get_results("SELECT * FROM {$leadguard_wcc_table} ORDER BY created_at DESC LIMIT 200");
}
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$leadguard_wcc_counts = $wpdb->get_results("SELECT status, COUNT(*) c FROM {$leadguard_wcc_table} GROUP BY status", OBJECT_K);
?>
<div class="wrap">
    <h1>WCC Lead &amp; Bot Guard â€” Submissions</h1>
    <ul class="subsubsub">
        <li><a href="?page=leadguard-wcc">All</a> |</li>
        <li><a href="?page=leadguard-wcc&status=clean">Clean (
                <?php echo (int) ($leadguard_wcc_counts['clean']->c ?? 0); ?>)
            </a> |
        </li>
        <li><a href="?page=leadguard-wcc&status=suspicious">Suspicious
                (
                <?php echo (int) ($leadguard_wcc_counts['suspicious']->c ?? 0); ?>)
            </a> |</li>
        <li><a href="?page=leadguard-wcc&status=rejected">Rejected
                (
                <?php echo (int) ($leadguard_wcc_counts['rejected']->c ?? 0); ?>)
            </a></li>
    </ul>

    <!-- Bulk delete -->
    <form method="post" style="margin:12px 0;"
        onsubmit="return confirm('Delete <?php echo $leadguard_wcc_filter ? esc_js(strtoupper($leadguard_wcc_filter)) . ' ' : 'ALL '; ?>submissions? This cannot be undone.');">
        <?php wp_nonce_field('leadguard_wcc_row_action'); ?>
        <input type="hidden" name="leadguard_wcc_id" value="0">
        <input type="hidden" name="leadguard_wcc_action" value="delete_list">
        <input type="hidden" name="delete_list_status" value="<?php echo esc_attr($leadguard_wcc_filter); ?>">
        <button class="button button-secondary" style="color:#d63638;border-color:#d63638;">
            ðŸ—‘ Delete
            <?php echo $leadguard_wcc_filter ? esc_html(strtoupper($leadguard_wcc_filter)) . ' list' : 'ALL submissions'; ?>
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
            <?php foreach ($leadguard_wcc_rows as $leadguard_wcc_r): ?>
                <?php
                // Source badge config
                $leadguard_wcc_src_labels = [
                    'meta_ads' => ['ðŸ“˜ Meta Ads', '#1877f2'],
                    'google_ads' => ['ðŸ”  Google Ads', '#34a853'],
                    'tiktok_ads' => ['ðŸŽµ TikTok Ads', '#010101'],
                    'bing_ads' => ['ðŸ…± Bing Ads', '#0078d4'],
                    'twitter_ads' => ['ð •  Twitter Ads', '#1da1f2'],
                    'linkedin_ads' => ['in LinkedIn Ads', '#0a66c2'],
                    'snapchat_ads' => ['ðŸ‘» Snapchat Ads', '#fffc00'],
                    'paid_utm' => ['ðŸ’° Paid (UTM)', '#e67e22'],
                    'utm_organic' => ['ðŸ”— UTM Organic', '#8e44ad'],
                    'organic_search' => ['ðŸ”Ž Organic Search', '#27ae60'],
                    'organic_social' => ['ðŸ“£ Organic Social', '#2980b9'],
                    'referral' => ['â†© Referral', '#7f8c8d'],
                    'direct' => ['ðŸ”— Direct', '#95a5a6'],
                ];
                ?>
                <tr>
                    <td>
                        <?php echo esc_html($leadguard_wcc_r->created_at); ?>
                    </td>
                    <td>
                        <?php echo esc_html($leadguard_wcc_r->name); ?>
                    </td>
                    <td>
                        <?php echo esc_html($leadguard_wcc_r->email); ?>
                    </td>
                    <td>
                        <?php echo esc_html($leadguard_wcc_r->phone); ?>
                    </td>
                    <td>
                        <?php
                        $leadguard_wcc_src = $leadguard_wcc_r->traffic_source ?? 'direct';
                        [$leadguard_wcc_src_label, $leadguard_wcc_src_color] = $leadguard_wcc_src_labels[$leadguard_wcc_src] ?? [$leadguard_wcc_src, '#999'];
                        $leadguard_wcc_text_color = ($leadguard_wcc_src === 'snapchat_ads') ? '#000' : '#fff';
                        echo '<span style="background:' . esc_attr($leadguard_wcc_src_color) . ';color:' . esc_attr($leadguard_wcc_text_color) . ';padding:2px 7px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;">' . esc_html($leadguard_wcc_src_label) . '</span>';
                        if (!empty($leadguard_wcc_r->utm_params)) {
                            $leadguard_wcc_utms = json_decode($leadguard_wcc_r->utm_params, true);
                            if (is_array($leadguard_wcc_utms) && !empty($leadguard_wcc_utms)) {
                                $leadguard_wcc_utms_str = implode(' | ', array_map(fn($k, $v) => esc_html($k) . '=' . esc_html($v), array_keys($leadguard_wcc_utms), $leadguard_wcc_utms));
                                echo '<br><span style="font-size:10px;color:#666;">' . esc_html($leadguard_wcc_utms_str) . '</span>';
                            }
                        }
                        ?>
                    </td>
                    <td>
                        <?php echo esc_html($leadguard_wcc_r->ip_address); ?>
                    </td>
                    <td style="font-size:11px;">
                        <?php
                        $leadguard_wcc_dev_key = $leadguard_wcc_r->device_id ?: $leadguard_wcc_r->device_fingerprint;
                        if ($leadguard_wcc_dev_key) {
                            $leadguard_wcc_dev_short = substr($leadguard_wcc_dev_key, 0, 8) . 'â€¦';
                            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            $leadguard_wcc_dev_bad_count = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$leadguard_wcc_table} WHERE (device_id=%s OR device_fingerprint=%s) AND status IN ('rejected','suspicious')",
                                $leadguard_wcc_dev_key,
                                $leadguard_wcc_dev_key
                            ));
                            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            echo '<code title="' . esc_attr($leadguard_wcc_dev_key) . '">' . esc_html($leadguard_wcc_dev_short) . '</code>';
                            if ($leadguard_wcc_dev_bad_count > 1) {
                                echo ' <span style="color:#d63638;font-weight:700;">Ã—' . (int) $leadguard_wcc_dev_bad_count . '</span>';
                            }
                        } else {
                            echo '<span style="color:#d63638;">no cookie</span>';
                        }
                        ?>
                    </td>
                    <td><strong>
                            <?php echo (int) $leadguard_wcc_r->fraud_score; ?>
                        </strong></td>
                    <td>
                        <?php
                        $leadguard_wcc_color = match ($leadguard_wcc_r->status) { 'clean' => '#2271b1', 'suspicious' => '#dba617', default => '#d63638'};
                        echo '<span style="color:' . esc_attr($leadguard_wcc_color) . ';font-weight:600;">' . esc_html(strtoupper($leadguard_wcc_r->status)) . '</span>';
                        ?>
                    </td>
                    <td style="max-width:220px;font-size:11px;color:#666;">
                        <?php echo esc_html($leadguard_wcc_r->reasons); ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline;white-space:nowrap;">
                            <?php wp_nonce_field('leadguard_wcc_row_action'); ?>
                            <input type="hidden" name="leadguard_wcc_id" value="<?php echo (int) $leadguard_wcc_r->id; ?>">
                            <button class="button button-small" name="leadguard_wcc_action" value="mark_fake">Block
                                IP+Email</button>
                            <button class="button button-small" name="leadguard_wcc_action" value="mark_clean">Mark
                                Clean</button>
                            <button class="button button-small" name="leadguard_wcc_action" value="delete"
                                style="color:#d63638;border-color:#d63638;"
                                onclick="return confirm('Delete this submission?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
