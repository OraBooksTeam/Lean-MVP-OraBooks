<div class="orabooks-notification-preferences">
    <h2><?php esc_html_e('Notification Preferences', 'orabooks'); ?></h2>
    <form id="orabooks-nc-prefs-form" class="orabooks-form">
        <div class="orabooks-form-group">
            <label><?php esc_html_e('Channels', 'orabooks'); ?></label>
            <label><input type="checkbox" name="channels[]" value="email" checked> <?php esc_html_e('Email', 'orabooks'); ?></label>
            <label><input type="checkbox" name="channels[]" value="push"> <?php esc_html_e('Push', 'orabooks'); ?></label>
            <label><input type="checkbox" name="channels[]" value="inapp" checked> <?php esc_html_e('In-app', 'orabooks'); ?></label>
        </div>
        <div class="orabooks-form-group">
            <label for="prefs-quiet-start"><?php esc_html_e('Quiet hours start', 'orabooks'); ?></label>
            <input type="time" id="prefs-quiet-start" name="quiet_hours_start">
        </div>
        <div class="orabooks-form-group">
            <label for="prefs-quiet-end"><?php esc_html_e('Quiet hours end', 'orabooks'); ?></label>
            <input type="time" id="prefs-quiet-end" name="quiet_hours_end">
        </div>
        <div class="orabooks-form-group">
            <label for="prefs-digest"><?php esc_html_e('Digest', 'orabooks'); ?></label>
            <select id="prefs-digest" name="digest">
                <option value="none"><?php esc_html_e('None', 'orabooks'); ?></option>
                <option value="daily"><?php esc_html_e('Daily', 'orabooks'); ?></option>
                <option value="weekly"><?php esc_html_e('Weekly', 'orabooks'); ?></option>
            </select>
        </div>
        <div class="orabooks-form-group">
            <label for="prefs-language"><?php esc_html_e('Language', 'orabooks'); ?></label>
            <select id="prefs-language" name="language">
                <option value="en"><?php esc_html_e('English', 'orabooks'); ?></option>
            </select>
        </div>
        <div class="orabooks-form-group">
            <label><input type="checkbox" name="escalation_enabled" value="1" checked> <?php esc_html_e('Enable escalation', 'orabooks'); ?></label>
        </div>
        <div id="orabooks-nc-prefs-message" class="orabooks-message"></div>
        <button type="submit" class="orabooks-btn orabooks-btn-primary"><?php esc_html_e('Save Preferences', 'orabooks'); ?></button>
    </form>
</div>
