<?php
/**
 * Plugin Name: Flotila Upiti
 * Description: Log WPForms prijava, slanje emailova, chat s korisnicima, IMAP čitanje odgovora.
 * Version: 2.1
 * Author: Flotila
 */

if (!defined('ABSPATH')) exit;

define('FU_CPT',     'flotila_upit');
define('FU_OPT',     'fu_settings');

/* ═══════════════════════════════════════════════
   CPT
═══════════════════════════════════════════════ */
add_action('init', function () {
    register_post_type(FU_CPT, [
        'labels'   => ['name' => 'Upiti', 'singular_name' => 'Upit'],
        'public'   => false,
        'show_ui'  => false,
        'supports' => ['title'],
    ]);
});

/* ═══════════════════════════════════════════════
   SETTINGS HELPERS
═══════════════════════════════════════════════ */
define('FU_LOGO_URL', 'https://flotila.hr/wp-content/uploads/2026/04/flotila_logo_transparent_tight_h150.png');

function fu_settings(): array {
    return wp_parse_args(get_option(FU_OPT, []), [
        'admin_email'  => get_option('admin_email'),
        'from_name'    => 'Flotila',
        'from_email'   => 'info@flotila.hr',
        'logo_url'     => FU_LOGO_URL,
        'imap_host'    => '',
        'imap_port'    => '993',
        'imap_user'    => '',
        'imap_pass'    => '',
        'imap_folder'  => 'INBOX',
        'wa_enabled'   => '1',
        'wa_number'    => '385915938100',
        'forms'        => [],
    ]);
}

function fu_form_type_word(string $label): string {
    $l = mb_strtolower($label);
    if (preg_match('/ponud|zahtjev|zatraži/u', $l)) return 'zahtjev';
    return 'upit';
}

function fu_default_user_body(string $word = 'upit'): string {
    return '<p style="font-size:16px;color:#111118;margin:0 0 6px;">Pozdrav, <strong>{ime}</strong>!</p>
<p style="font-size:15px;color:#374151;line-height:1.7;margin:0 0 24px;">Primili smo tvoj ' . $word . ' i javit ćemo ti se uskoro.</p>
<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin:0 0 26px;">
  <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#94a3b8;margin:0 0 14px;">Tvoji podaci</p>
  {podaci}
</div>
<p style="font-size:14px;color:#64748b;line-height:1.6;margin:0 0 4px;">Srdačno,</p>
<p style="font-size:14px;font-weight:700;color:#0f2744;margin:0;">Ekipa Flotila</p>';
}

function fu_form_settings(string $form_id, string $name_hint = ''): array {
    $s     = fu_settings();
    $saved = $s['forms'][$form_id] ?? [];
    $label = $saved['label'] ?? $name_hint;
    if (!$label) {
        $posts = get_posts(['post_type' => FU_CPT, 'posts_per_page' => 1, 'fields' => 'ids',
            'orderby' => 'date', 'order' => 'DESC',
            'meta_query' => [['key' => '_form_id', 'value' => $form_id]]]);
        if ($posts) $label = get_post_meta($posts[0], '_form_name', true) ?: '';
    }
    $word = fu_form_type_word($label);
    return wp_parse_args($saved, [
        'label'         => $label ?: 'Forma #' . $form_id,
        'user_subject'  => 'Primili smo tvoj ' . $word . ' — Flotila',
        'user_body'     => fu_default_user_body($word),
        'admin_subject' => 'Nova prijava: {naziv_forme}',
    ]);
}

function fu_replace(string $text, array $vars): string {
    foreach ($vars as $k => $v) $text = str_replace('{' . $k . '}', $v, $text);
    return $text;
}

function fu_label_to_key(string $label): string {
    $label = strtr($label, ['š'=>'s','č'=>'c','ć'=>'c','ž'=>'z','đ'=>'d','Š'=>'S','Č'=>'C','Ć'=>'C','Ž'=>'Z','Đ'=>'D']);
    $label = mb_strtolower($label);
    $label = preg_replace('/[^a-z0-9]+/', '_', $label);
    return trim($label, '_');
}

function fu_ref_tag(int $id): string { return '[REF:' . $id . ']'; }

/* ═══════════════════════════════════════════════
   HTML EMAIL WRAPPER
═══════════════════════════════════════════════ */
function fu_html_email(string $body_html): string {
    $s        = fu_settings();
    $logo_url = esc_url($s['logo_url'] ?: FU_LOGO_URL);
    $site_url = home_url();

    // If plain text (no HTML tags), convert to safe HTML
    if (!preg_match('/<[a-z][\s\S]*>/i', $body_html)) {
        $paragraphs = explode("\n\n", trim($body_html));
        $converted  = '';
        foreach ($paragraphs as $p) {
            $p = nl2br(esc_html(trim($p)));
            if ($p !== '') $converted .= '<p style="margin:0 0 16px;color:#374151;font-size:15px;line-height:1.7;">' . $p . '</p>';
        }
        $body_html = $converted;
    }

    return '<!DOCTYPE html>
<html lang="hr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:Inter,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:48px 16px;">
<tr><td align="center">
<table width="580" cellpadding="0" cellspacing="0" style="max-width:580px;width:100%;">

  <!-- Header -->
  <tr><td style="background:#0f2744;padding:28px 36px;border-radius:16px 16px 0 0;text-align:center;">
    <a href="' . $site_url . '" style="display:inline-block;">
      <img src="' . $logo_url . '" alt="Flotila" height="46" style="height:46px;max-width:180px;display:block;border:0;">
    </a>
  </td></tr>

  <!-- Body -->
  <tr><td style="background:#ffffff;padding:38px 40px 32px;">
    ' . $body_html . '
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#ffffff;padding:0 40px 32px;border-radius:0 0 16px 16px;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td style="border-top:1px solid #e2e8f0;padding-top:20px;text-align:center;">
        <p style="margin:0 0 4px;font-size:12px;color:#94a3b8;">
          <a href="' . $site_url . '" style="color:#1565e8;text-decoration:none;font-weight:600;">flotila.hr</a>
        </p>
        <p style="margin:0;font-size:11px;color:#cbd5e1;">Ovaj email je poslan automatski. Možeš mu odgovoriti direktno.</p>
      </td></tr>
    </table>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>';
}

function fu_html_headers(): array {
    $s = fu_settings();
    return [
        'From: ' . $s['from_name'] . ' <' . $s['from_email'] . '>',
        'Reply-To: ' . $s['from_email'],
        'Content-Type: text/html; charset=UTF-8',
        'MIME-Version: 1.0',
    ];
}

function fu_plain_headers(): array {
    $s = fu_settings();
    return [
        'From: ' . $s['from_name'] . ' <' . $s['from_email'] . '>',
        'Reply-To: ' . $s['from_email'],
        'Content-Type: text/plain; charset=UTF-8',
    ];
}

/* ═══════════════════════════════════════════════
   MOBILE TOP OVERSCROLL / WHITE FLASH FIX
═══════════════════════════════════════════════ */
// OpenGraph defaults via Rank Math filtere (bez duplikata)
define('FU_OG_IMAGE',       'https://flotila.hr/wp-content/uploads/2026/04/flotila_logo_transparent_tight_h150.png');
define('FU_OG_FRONT_TITLE', 'Flotila - Zaraduj vozeci vec danas');
define('FU_OG_FRONT_DESC',  'Flotila je platforma za vozače koji žele voziti i zarađivati. Mi vodimo sve oko obrta — ti samo voziš.');

add_filter('rank_math/frontend/title', function ($title) {
    if (is_front_page()) return FU_OG_FRONT_TITLE;
    return $title;
});
add_filter('rank_math/frontend/description', function ($desc) {
    if (is_front_page() && empty(trim((string) $desc))) return FU_OG_FRONT_DESC;
    return $desc;
});
add_filter('rank_math/opengraph/facebook/image', function ($image) {
    return $image ?: FU_OG_IMAGE;
});
add_filter('rank_math/opengraph/twitter/image', function ($image) {
    return $image ?: FU_OG_IMAGE;
});

add_action('wp_head', function () { ?>
<script>
(function(){
  var s = document.createElement('style');
  s.id = 'fu-hero-flash-fix';
  s.textContent = 'html{background:#0F2744}';
  document.head.appendChild(s);
  document.addEventListener('DOMContentLoaded', function(){
    if (!document.querySelector('.hero, .vz-market-hero')) {
      s.remove();
    }
  });
})();
</script>
<style id="fu-mobile-top-gap-fix">
  html{
    background:#F6F8FC!important;
    overscroll-behavior-y:none;
  }
  html:has(.hero){
    background:#0F2744!important;
  }
  body{
    background:#F6F8FC!important;
    -webkit-backface-visibility:hidden;
    backface-visibility:hidden;
  }

  html:not(.vz-modal-open),
  html:not(.vz-modal-open) body{
    touch-action:pan-y pinch-zoom!important;
    overflow-y:auto!important;
  }

  .flotila-marquee,
  .cars-scroll,
  .vz-cars-track,
  .pl-l,
  .ft-items,
  .sj-points,
  .dg,
  .stp-g,
  .comp-card-loop,
  .pain-cards,
  .sj4,
  .sv-l,
  #suradnja [data-eco-panel="partneri"] .prt-g{
    touch-action:pan-x pan-y!important;
  }

  @media (min-width:783px){
    html.vz-modal-open,
    html.vz-modal-open body{
      touch-action:none!important;
      overflow:hidden!important;
    }
  }

  @media (max-width:900px), (pointer:coarse){
    html,
    body,
    html.vz-modal-open,
    html.vz-modal-open body,
    body.vz-modal-open{
      overflow-y:auto!important;
      touch-action:auto!important;
      position:static!important;
      height:auto!important;
    }

    .vz-popup-overlay{
      touch-action:auto!important;
    }
  }

  @media (max-width:782px){
    html{
      background:#F6F8FC!important;
    }
    html:has(.hero){
      background:#0F2744!important;
    }
    body{
      background:#F6F8FC!important;
      min-height:100svh;
    }
    body:has(.hero){
      padding-top:0!important;
    }
    body:has(.hero) #content,
    body:has(.hero) #primary,
    body:has(.hero) .site,
    body:has(.hero) .site-content,
    body:has(.hero) .site-main,
    body:has(.hero) .page,
    body:has(.hero) .page-content,
    body:has(.hero) .content-area,
    body:has(.hero) .ast-container,
    body:has(.hero) .site-inner,
    body:has(.hero) main,
    body:has(.hero) .entry-content,
    body:has(.hero) .wp-block-post-content,
    body:has(.hero) .elementor,
    body:has(.hero) .elementor-section-wrap,
    body:has(.hero) .elementor-widget-wrap,
    body:has(.hero) .elementor-widget-container{
      margin-top:0!important;
      padding-top:0!important;
      background:transparent!important;
    }

    body:has(.hero) .entry-content>:first-child,
    body:has(.hero) .wp-block-post-content>:first-child,
    body:has(.hero) .elementor-widget-container>:first-child{
      margin-top:0!important;
    }

    body:has(.hero) .entry-content>.wp-block-spacer:first-child,
    body:has(.hero) .wp-block-post-content>.wp-block-spacer:first-child,
    body:has(.hero) .elementor-widget-container>.wp-block-spacer:first-child,
    body:has(.hero) .entry-content>.elementor-spacer:first-child,
    body:has(.hero) .wp-block-post-content>.elementor-spacer:first-child,
    body:has(.hero) .elementor-widget-container>.elementor-spacer:first-child,
    body:has(.hero) .entry-content>p:empty,
    body:has(.hero) .wp-block-post-content>p:empty,
    body:has(.hero) .elementor-widget-container>p:empty,
    body:has(.hero) .entry-content>br:first-child,
    body:has(.hero) .wp-block-post-content>br:first-child,
    body:has(.hero) .elementor-widget-container>br:first-child{
      display:none!important;
      height:0!important;
      min-height:0!important;
      margin:0!important;
      padding:0!important;
      overflow:hidden!important;
    }
  }
</style>
<?php }, 1);

/* ═══════════════════════════════════════════════
   ANDROID ONE-FINGER SCROLL SAFETY NET
═══════════════════════════════════════════════ */
add_action('wp_footer', function () { ?>
<style id="fu-android-scroll-final">
  @media (max-width:900px), (pointer:coarse){
    html,
    body,
    html.vz-modal-open,
    html.vz-modal-open body,
    body.vz-modal-open{
      overflow-y:auto!important;
      touch-action:auto!important;
      position:static!important;
      height:auto!important;
    }

    html:not(.vz-modal-open) .flotila-marquee,
    html:not(.vz-modal-open) .cars-scroll,
    html:not(.vz-modal-open) .vz-cars-track,
    html:not(.vz-modal-open) .pl-l,
    html:not(.vz-modal-open) .ft-items,
    html:not(.vz-modal-open) .sj-points,
    html:not(.vz-modal-open) .dg,
    html:not(.vz-modal-open) .stp-g,
    html:not(.vz-modal-open) .comp-card-loop,
    html:not(.vz-modal-open) .pain-cards,
    html:not(.vz-modal-open) .sj4,
    html:not(.vz-modal-open) .sv-l,
    html:not(.vz-modal-open) #suradnja [data-eco-panel="partneri"] .prt-g{
      touch-action:pan-x pan-y pinch-zoom!important;
    }
  }
</style>
<script id="fu-android-scroll-final-js">
(function(){
  function hasRealVisiblePopup(){
    return Array.prototype.some.call(document.querySelectorAll('.vz-popup-overlay'),function(popup){
      if(!(popup instanceof HTMLElement))return false;
      if(popup.classList.contains('calc-hover-popup')||popup.dataset.hoverOpen==='1')return false;
      var style=window.getComputedStyle(popup);
      var rect=popup.getBoundingClientRect();
      return rect.width>0&&rect.height>0&&style.display!=='none'&&style.visibility!=='hidden'&&style.opacity!=='0';
    });
  }

  function unlockStaleScroll(){
    var html=document.documentElement;
    var body=document.body;
    if(!html||!body)return;
    if(!(html.classList.contains('vz-modal-open')||body.classList.contains('vz-modal-open')))return;
    if(hasRealVisiblePopup())return;
    html.classList.remove('vz-modal-open');
    body.classList.remove('vz-modal-open');
    html.style.removeProperty('overflow');
    body.style.removeProperty('overflow');
    html.style.removeProperty('touch-action');
    body.style.removeProperty('touch-action');
    html.style.removeProperty('--vz-scrollbar-width');
    body.style.removeProperty('padding-right');
  }

  window.addEventListener('pageshow',unlockStaleScroll,{passive:true});
  window.addEventListener('touchstart',unlockStaleScroll,{passive:true,capture:true});
  window.addEventListener('touchmove',unlockStaleScroll,{passive:true,capture:true});
  window.addEventListener('pointerdown',unlockStaleScroll,{passive:true,capture:true});
  window.addEventListener('pointermove',unlockStaleScroll,{passive:true,capture:true});
  window.addEventListener('scroll',unlockStaleScroll,{passive:true});
  window.setInterval(unlockStaleScroll,500);
})();
</script>
<?php }, 9999);

/* ═══════════════════════════════════════════════
   WPFORMS HOOK
═══════════════════════════════════════════════ */
add_action('wpforms_process_complete', function ($fields, $entry, $form_data, $entry_id) {

    $form_id   = (string) $form_data['id'];
    $form_name = $form_data['settings']['form_title'] ?? 'Forma #' . $form_id;
    $fs        = fu_form_settings($form_id, $form_name);
    $s         = fu_settings();

    $submitter_email = '';
    $submitter_name  = '';
    $lines           = [];
    $lines_user      = [];
    $fields_user_raw = []; // label+value za HTML tablicu

    foreach ($fields as $field) {
        $label = trim($field['name']  ?? '');
        $value = trim($field['value'] ?? '');
        if ($value === '') continue;
        $line  = ($label ? $label . ': ' : '') . $value;
        $lines[] = $line;
        if (!in_array($field['type'], ['checkbox', 'gdpr-checkbox', 'consent'], true)) {
            $lines_user[]      = $line;
            $fields_user_raw[] = ['label' => $label, 'value' => $value];
        }
        if ($field['type'] === 'email' && !$submitter_email)
            $submitter_email = sanitize_email($value);
        if (!$submitter_name && ($field['type'] === 'name' || ($field['type'] === 'text' && preg_match('/ime|name|naziv/i', $label))))
            $submitter_name = sanitize_text_field($value);
    }

    $fields_text      = implode("\n", $lines);
    $fields_text_user = implode("\n", $lines_user);

    // Individualni placeholderi po labeli i ID-u (npr. {mobitel}, {field_7})
    $field_vars = [];
    foreach ($fields as $field_id => $field) {
        $label = trim($field['name'] ?? '');
        $value = trim($field['value'] ?? '');
        if ($value === '') continue;
        $field_vars['field_' . $field_id] = $value;
        if ($label) $field_vars[fu_label_to_key($label)] = $value;
    }

    // Save
    $post_id = wp_insert_post([
        'post_type'   => FU_CPT,
        'post_title'  => $form_name . ' — ' . current_time('d.m.Y. H:i'),
        'post_status' => 'publish',
        'post_date'   => current_time('mysql'),
    ]);
    if (!$post_id || is_wp_error($post_id)) return;

    // Lifetime counter — nikad se ne smanjuje, čak ni kad se upit obriše
    $total = (int) get_option('fu_total_count', 0);
    update_option('fu_total_count', $total + 1, false);

    update_post_meta($post_id, '_form_id',        $form_id);
    update_post_meta($post_id, '_form_name',       $form_name);
    update_post_meta($post_id, '_fields_data',     $fields);
    update_post_meta($post_id, '_fields_text',     $fields_text);
    update_post_meta($post_id, '_submitter_email', $submitter_email);
    update_post_meta($post_id, '_submitter_name',  $submitter_name);
    update_post_meta($post_id, '_ip',              $_SERVER['REMOTE_ADDR'] ?? '');
    update_post_meta($post_id, '_read',            0);
    update_post_meta($post_id, '_thread', [[
        'dir'  => 'in',
        'date' => current_time('d.m.Y. H:i'),
        'from' => trim(($submitter_name ? $submitter_name . ' ' : '') . ($submitter_email ? '<' . $submitter_email . '>' : '')),
        'body' => $fields_text,
    ]]);

    // Admin vidi sve (uključujući checkbox); korisnik ne vidi checkbox
    $vars_admin = [
        'ime'         => $submitter_name ?: 'korisnik',
        'email'       => $submitter_email,
        'naziv_forme' => $form_name,
        'podaci'      => $fields_text,
        'datum'       => current_time('d.m.Y. H:i'),
    ];
    // Svako polje kao styled tablica u HTML emailu
    $row_html = '';
    foreach ($fields_user_raw as $f) {
        $row_html .= '<tr>'
            . '<td style="padding:9px 16px 9px 0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;white-space:nowrap;vertical-align:top;">' . esc_html($f['label']) . '</td>'
            . '<td style="padding:9px 0;font-size:14px;color:#1e293b;line-height:1.5;vertical-align:top;">' . nl2br(esc_html($f['value'])) . '</td>'
            . '</tr>';
    }
    $fields_text_user_html = $row_html
        ? '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">' . $row_html . '</table>'
        : nl2br(esc_html($fields_text_user));
    $vars_user = array_merge($field_vars, $vars_admin, [
        'podaci' => $fields_text_user_html,
    ]);

    // Admin (plain text — sve uključujući checkbox)
    $admin_body = "Nova prijava: {naziv_forme}\nDatum: {datum}\n" .
        ($submitter_email ? "Email: {email}\n" : '') .
        "\n{podaci}\n\n" .
        "Log: " . admin_url('admin.php?page=flotila-upiti&view=' . $post_id);
    wp_mail($s['admin_email'], fu_replace(esc_html($fs['admin_subject']), $vars_admin), fu_replace($admin_body, $vars_admin), fu_plain_headers());

    // User (HTML) — bez checkboxova
    if ($submitter_email) {
        $subj     = fu_replace($fs['user_subject'], $vars_user) . ' ' . fu_ref_tag($post_id);
        $body_html = fu_replace($fs['user_body'], $vars_user);
        wp_mail($submitter_email, $subj, fu_html_email($body_html), fu_html_headers());
    }

}, 10, 4);

/* ═══════════════════════════════════════════════
   IMAP
═══════════════════════════════════════════════ */
add_action('fu_imap_check', 'fu_imap_fetch');

function fu_imap_fetch(): int {
    if (!function_exists('imap_open')) return 0;
    $s = fu_settings();
    if (!$s['imap_host'] || !$s['imap_user'] || !$s['imap_pass']) return 0;

    $dsn  = '{' . $s['imap_host'] . ':' . $s['imap_port'] . '/imap/ssl}' . $s['imap_folder'];
    $mbox = @imap_open($dsn, $s['imap_user'], $s['imap_pass'], 0, 1);
    if (!$mbox) return 0;

    $found = 0;
    $uids  = @imap_search($mbox, 'UNSEEN SUBJECT "[REF:"');
    if ($uids) foreach ($uids as $uid) {
        $hdr     = imap_headerinfo($mbox, $uid);
        $subject = isset($hdr->subject) ? imap_utf8($hdr->subject) : '';
        if (!preg_match('/\[REF:(\d+)\]/', $subject, $m)) continue;
        $post_id = (int) $m[1];
        $post    = get_post($post_id);
        if (!$post || $post->post_type !== FU_CPT) continue;

        $struct = imap_fetchstructure($mbox, $uid);
        $body   = fu_imap_body($mbox, $uid, $struct);
        $body   = fu_strip_quoted($body);
        if (trim($body) === '') continue;

        $from = '';
        if (!empty($hdr->from[0])) {
            $f    = $hdr->from[0];
            $from = (isset($f->personal) ? imap_utf8($f->personal) . ' ' : '') . '<' . $f->mailbox . '@' . $f->host . '>';
        }

        $thread   = get_post_meta($post_id, '_thread', true) ?: [];
        $thread[] = ['dir' => 'in', 'date' => date('d.m.Y. H:i', strtotime($hdr->date ?? 'now')), 'from' => $from, 'body' => $body];
        update_post_meta($post_id, '_thread', $thread);
        update_post_meta($post_id, '_read', 0);
        imap_setflag_full($mbox, (string) $uid, '\\Seen');
        $found++;
    }
    imap_close($mbox);
    return $found;
}

function fu_imap_body($mbox, $uid, $structure): string {
    if (empty($structure->parts)) {
        $b = imap_fetchbody($mbox, $uid, '1');
        if (isset($structure->encoding)) {
            if ($structure->encoding == 3) $b = base64_decode($b);
            if ($structure->encoding == 4) $b = quoted_printable_decode($b);
        }
        return $b;
    }
    foreach ($structure->parts as $i => $part) {
        if (strtoupper($part->subtype) === 'PLAIN') {
            $b = imap_fetchbody($mbox, $uid, (string)($i + 1));
            if ($part->encoding == 3) $b = base64_decode($b);
            if ($part->encoding == 4) $b = quoted_printable_decode($b);
            return $b;
        }
    }
    return '';
}

function fu_strip_quoted(string $b): string {
    $b = preg_replace('/^(>.*\n?)+/m', '', $b);
    $b = preg_replace('/\n?On .+ wrote:\s*$/s', '', $b);
    $b = preg_replace('/\n?-{3,}.*Original Message.*-{3,}.*/si', '', $b);
    return trim($b);
}

function fu_imap_test(): array {
    $s = fu_settings();

    if (!function_exists('imap_open')) {
        return ['ok' => false, 'msg' => 'PHP imap ekstenzija nije dostupna na ovom serveru. Kontaktiraj Hostinger support i traži da uključe php_imap.'];
    }

    if (!$s['imap_host'] || !$s['imap_user'] || !$s['imap_pass']) {
        return ['ok' => false, 'msg' => 'IMAP postavke nisu popunjene.'];
    }

    $dsn = '{' . $s['imap_host'] . ':' . $s['imap_port'] . '/imap/ssl}' . $s['imap_folder'];
    imap_timeout(IMAP_OPENTIMEOUT, 10);
    $mbox = @imap_open($dsn, $s['imap_user'], $s['imap_pass'], 0, 1);

    if (!$mbox) {
        $err = imap_last_error() ?: 'Nepoznata greška';
        return ['ok' => false, 'msg' => 'Veza neuspješna: ' . $err];
    }

    $count = imap_num_msg($mbox);
    imap_close($mbox);
    return ['ok' => true, 'msg' => 'Veza uspješna! Inbox sadrži ' . $count . ' emailova.'];
}

add_filter('cron_schedules', function ($s) {
    $s['fu_5min'] = ['interval' => 300, 'display' => 'Svakih 5 minuta'];
    return $s;
});
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('fu_imap_check')) wp_schedule_event(time(), 'fu_5min', 'fu_imap_check');
});
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('fu_imap_check');
});

/* ═══════════════════════════════════════════════
   ADMIN_INIT — handle all redirecting actions
   (must run before any HTML output)
═══════════════════════════════════════════════ */
add_action('admin_init', function () {
    if (empty($_GET['page']) || !in_array($_GET['page'], ['flotila-upiti', 'flotila-upiti-settings'])) return;
    if (!current_user_can('manage_options')) return;

    // Delete
    if (!empty($_GET['delete'])) {
        $id = intval($_GET['delete']);
        check_admin_referer('fu_delete_' . $id);
        wp_delete_post($id, true);
        wp_safe_redirect(admin_url('admin.php?page=flotila-upiti&deleted=1'));
        exit;
    }

    // Manual IMAP check
    if (!empty($_GET['imap_check'])) {
        check_admin_referer('fu_imap_check');
        $found = fu_imap_fetch();
        wp_safe_redirect(admin_url('admin.php?page=flotila-upiti&imap_done=' . $found));
        exit;
    }

    // Send reply
    if (!empty($_POST['fu_reply_send'])) {
        $post_id = intval($_POST['fu_post_id'] ?? 0);
        check_admin_referer('fu_reply_' . $post_id);

        $post = get_post($post_id);
        if (!$post || $post->post_type !== FU_CPT) {
            wp_safe_redirect(admin_url('admin.php?page=flotila-upiti'));
            exit;
        }

        $to      = sanitize_email($_POST['fu_reply_to'] ?? '');
        $subject = sanitize_text_field($_POST['fu_reply_subject'] ?? '');
        $message = sanitize_textarea_field($_POST['fu_reply_message'] ?? '');

        if (!$to || !$message) {
            wp_safe_redirect(admin_url('admin.php?page=flotila-upiti&view=' . $post_id . '&reply_error=1'));
            exit;
        }

        $ref = fu_ref_tag($post_id);
        $subj_with_ref = (strpos($subject, '[REF:') === false) ? $subject . ' ' . $ref : $subject;

        $sent = wp_mail($to, $subj_with_ref, fu_html_email($message), fu_html_headers());

        if ($sent) {
            $thread   = get_post_meta($post_id, '_thread', true) ?: [];
            $thread[] = [
                'dir'     => 'out',
                'date'    => current_time('d.m.Y. H:i'),
                'to'      => $to,
                'subject' => $subject,
                'body'    => $message,
                'by'      => wp_get_current_user()->display_name,
            ];
            update_post_meta($post_id, '_thread', $thread);
        }

        wp_safe_redirect(admin_url('admin.php?page=flotila-upiti&view=' . $post_id . '&' . ($sent ? 'reply_sent' : 'reply_fail') . '=1'));
        exit;
    }

    // IMAP test
    if (!empty($_GET['imap_test'])) {
        check_admin_referer('fu_imap_test');
        $result = fu_imap_test();
        set_transient('fu_imap_test_result', $result, 60);
        wp_safe_redirect(admin_url('admin.php?page=flotila-upiti-settings#imap'));
        exit;
    }

    // Save settings
    if (!empty($_POST['fu_save_settings'])) {
        check_admin_referer('fu_save_settings');

        $new = [
            'admin_email' => sanitize_email($_POST['admin_email'] ?? ''),
            'from_name'   => sanitize_text_field($_POST['from_name'] ?? ''),
            'from_email'  => sanitize_email($_POST['from_email'] ?? ''),
            'logo_url'    => esc_url_raw($_POST['logo_url'] ?? ''),
            'imap_host'   => sanitize_text_field($_POST['imap_host'] ?? ''),
            'imap_port'   => sanitize_text_field($_POST['imap_port'] ?? '993'),
            'imap_user'   => sanitize_text_field($_POST['imap_user'] ?? ''),
            'imap_pass'   => sanitize_text_field($_POST['imap_pass'] ?? ''),
            'imap_folder' => sanitize_text_field($_POST['imap_folder'] ?? 'INBOX'),
            'wa_enabled'  => isset($_POST['wa_enabled']) ? '1' : '0',
            'wa_number'   => preg_replace('/\D/', '', $_POST['wa_number'] ?? ''),
            'forms'       => [],
        ];

        if (!empty($_POST['forms']) && is_array($_POST['forms'])) {
            foreach ($_POST['forms'] as $fid => $fdata) {
                $new['forms'][sanitize_text_field($fid)] = [
                    'label'         => sanitize_text_field($fdata['label'] ?? ''),
                    'user_subject'  => sanitize_text_field($fdata['user_subject'] ?? ''),
                    'user_body'     => wp_kses_post($fdata['user_body'] ?? ''),
                    'admin_subject' => sanitize_text_field($fdata['admin_subject'] ?? ''),
                ];
            }
        }

        update_option(FU_OPT, $new);
        wp_safe_redirect(admin_url('admin.php?page=flotila-upiti-settings&saved=1'));
        exit;
    }
});

/* ═══════════════════════════════════════════════
   ADMIN MENU
═══════════════════════════════════════════════ */
add_action('admin_menu', function () {
    add_menu_page('Flotila Upiti', 'Upiti', 'manage_options',
        'flotila-upiti', 'fu_page', 'dashicons-email-alt2', 26);
    add_submenu_page('flotila-upiti', 'Postavke', 'Postavke', 'manage_options',
        'flotila-upiti-settings', 'fu_settings_page');
});

add_action('admin_menu', function () {
    global $menu;
    $unread = (int)(new WP_Query([
        'post_type' => FU_CPT, 'posts_per_page' => -1,
        'post_status' => 'publish', 'fields' => 'ids',
        'meta_query' => [['key' => '_read', 'value' => '0']],
    ]))->found_posts;
    if (!$unread) return;
    foreach ($menu as &$item) {
        if (($item[2] ?? '') === 'flotila-upiti') {
            $item[0] .= ' <span class="awaiting-mod">' . $unread . '</span>';
            break;
        }
    }
}, 99);

/* ═══════════════════════════════════════════════
   PAGE ROUTER (no redirects here)
═══════════════════════════════════════════════ */
function fu_page() {
    if (!empty($_GET['view'])) { fu_single(intval($_GET['view'])); return; }
    fu_list();
}

/* ═══════════════════════════════════════════════
   LIST VIEW
═══════════════════════════════════════════════ */
function fu_list() {
    $posts = get_posts(['post_type' => FU_CPT, 'posts_per_page' => 200,
        'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']);

    $total   = (int) get_option('fu_total_count', 0);
    $active  = count($posts);
    $unread  = count(array_filter($posts, fn($p) => !get_post_meta($p->ID, '_read', true)));

    echo '<div class="wrap">' . fu_style();
    echo '<h1 class="wp-heading-inline">Flotila Upiti</h1>';
    echo '<div class="fu-stats-bar">
        <div class="fu-stat"><span class="fu-stat-num">' . $total . '</span><span class="fu-stat-lbl">Ukupno primljenih</span></div>
        <div class="fu-stat"><span class="fu-stat-num">' . $active . '</span><span class="fu-stat-lbl">U arhivi</span></div>
        <div class="fu-stat fu-stat-alert"><span class="fu-stat-num">' . $unread . '</span><span class="fu-stat-lbl">Nepročitano</span></div>
    </div>';

    if (fu_settings()['imap_host']) {
        $url = wp_nonce_url(admin_url('admin.php?page=flotila-upiti&imap_check=1'), 'fu_imap_check');
        echo ' <a href="' . esc_url($url) . '" class="page-title-action">Provjeri inbox ↻</a>';
    }

    if (!empty($_GET['deleted'])) echo '<div class="notice notice-success is-dismissible"><p>Upit obrisan.</p></div>';
    if (isset($_GET['imap_done'])) {
        $n = (int) $_GET['imap_done'];
        $msg = $n > 0
            ? 'Pronađen' . ($n === 1 ? '' : 'o') . ' <strong>' . $n . '</strong> novi' . ($n === 1 ? ' odgovor' : ($n < 5 ? 'a odgovora' : ' odgovora')) . ' od korisnika.'
            : 'Inbox provjeren — nema novih odgovora.';
        echo '<div class="notice notice-success is-dismissible"><p>' . $msg . '</p></div>';
    }

    if (empty($posts)) { echo '<p class="fu-empty">Još nema upita.</p></div>'; return; }

    echo '<table class="fu-table"><thead><tr>
        <th>Forma</th><th>Ime</th><th>Email</th><th>Datum</th><th>Status</th><th></th>
    </tr></thead><tbody>';

    foreach ($posts as $p) {
        $thread   = get_post_meta($p->ID, '_thread', true) ?: [];
        $out      = count(array_filter($thread, fn($m) => $m['dir'] === 'out'));
        $in_reply = count($thread) - 1;
        $read     = get_post_meta($p->ID, '_read', true);

        $badge = $read ? '<span class="fu-badge fu-badge-read">Pročitano</span>'
                       : '<span class="fu-badge fu-badge-new">Novo</span>';
        if ($out)             $badge .= ' <span class="fu-badge fu-badge-out">Odgovoreno</span>';
        if ($in_reply > 0 && $out) $badge .= ' <span class="fu-badge fu-badge-in">↩ ' . $in_reply . '</span>';

        $view = admin_url('admin.php?page=flotila-upiti&view=' . $p->ID);
        $del  = wp_nonce_url(admin_url('admin.php?page=flotila-upiti&delete=' . $p->ID), 'fu_delete_' . $p->ID);

        echo "<tr class='" . ($read ? '' : 'fu-row-new') . "'>
            <td>" . esc_html(get_post_meta($p->ID, '_form_name', true) ?: '—') . "</td>
            <td>" . esc_html(get_post_meta($p->ID, '_submitter_name', true) ?: '—') . "</td>
            <td>" . esc_html(get_post_meta($p->ID, '_submitter_email', true) ?: '—') . "</td>
            <td>" . get_the_date('d.m.Y. H:i', $p) . "</td>
            <td>{$badge}</td>
            <td style='white-space:nowrap;'>
                <a href='" . esc_url($view) . "' class='fu-btn'>Otvori</a>
                <a href='" . esc_url($del) . "' class='fu-btn fu-btn-del'
                   onclick='return confirm(\"Obrisati?\")'>×</a>
            </td></tr>";
    }
    echo '</tbody></table></div>';
}

/* ═══════════════════════════════════════════════
   SINGLE / CHAT VIEW
═══════════════════════════════════════════════ */
function fu_single(int $post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== FU_CPT) {
        echo '<div class="wrap"><p>Upit nije pronađen.</p></div>';
        return;
    }

    update_post_meta($post_id, '_read', 1);

    $form_name = get_post_meta($post_id, '_form_name', true);
    $email     = get_post_meta($post_id, '_submitter_email', true);
    $name      = get_post_meta($post_id, '_submitter_name', true);
    $thread    = get_post_meta($post_id, '_thread', true) ?: [];
    $fs        = fu_form_settings(get_post_meta($post_id, '_form_id', true));
    $del_url   = wp_nonce_url(admin_url('admin.php?page=flotila-upiti&delete=' . $post_id), 'fu_delete_' . $post_id);

    echo '<div class="wrap">' . fu_style();
    echo '<a href="' . esc_url(admin_url('admin.php?page=flotila-upiti')) . '" class="fu-back">← Natrag</a>';
    echo '<div class="fu-chat-header">
        <div>
            <h1 style="margin:0 0 3px;">' . esc_html($form_name) . '</h1>
            <p class="fu-meta">' . get_the_date('d.m.Y. H:i', $post) .
                ($email ? ' · <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>' : '') . '</p>
        </div>
        <a href="' . esc_url($del_url) . '" class="fu-btn fu-btn-del"
           onclick="return confirm(\'Obrisati?\')">Obriši</a>
    </div>';

    if (!empty($_GET['reply_sent'])) echo '<div class="notice notice-success is-dismissible"><p>Odgovor poslan.</p></div>';
    if (!empty($_GET['reply_fail'])) echo '<div class="notice notice-error is-dismissible"><p>Slanje nije uspjelo.</p></div>';
    if (!empty($_GET['reply_error'])) echo '<div class="notice notice-error is-dismissible"><p>Email i poruka su obavezni.</p></div>';

    // Chat thread
    echo '<div class="fu-chat-wrap">';
    foreach ($thread as $msg) {
        $out  = $msg['dir'] === 'out';
        $who  = $out
            ? esc_html($msg['by'] ?? 'Admin')
            : esc_html($msg['from'] ?? ($name ?: 'Korisnik'));
        echo '<div class="fu-msg ' . ($out ? 'fu-msg-out' : 'fu-msg-in') . '">
            <div class="fu-msg-who">' . $who . ' · ' . esc_html($msg['date']) . '</div>
            <div class="fu-msg-body">' . nl2br(esc_html($msg['body'])) . '</div>
        </div>';
    }
    echo '</div>';

    // Reply form
    if ($email) {
        $last_out  = array_filter($thread, fn($m) => $m['dir'] === 'out');
        $last_subj = $last_out ? end($last_out)['subject'] : $fs['user_subject'];
        $re_subj   = str_starts_with($last_subj, 'Re:') ? $last_subj : 'Re: ' . $last_subj;

        echo '<div class="fu-reply-box">
            <form method="post" action="' . esc_url(admin_url('admin.php?page=flotila-upiti')) . '">';
        wp_nonce_field('fu_reply_' . $post_id);
        echo '<input type="hidden" name="fu_post_id" value="' . esc_attr($post_id) . '">
              <input type="hidden" name="fu_reply_send" value="1">
              <div class="fu-form-row">
                <label>Prima</label>
                <input type="email" name="fu_reply_to" value="' . esc_attr($email) . '" class="fu-input" required>
              </div>
              <div class="fu-form-row">
                <label>Naslov</label>
                <input type="text" name="fu_reply_subject" value="' . esc_attr($re_subj) . '" class="fu-input">
              </div>
              <div class="fu-form-row">
                <label>Poruka</label>
                <textarea name="fu_reply_message" rows="5" class="fu-textarea" placeholder="Napiši odgovor..." required></textarea>
              </div>
              <button type="submit" class="fu-btn fu-btn-send">Pošalji ↑</button>
        </form></div>';
    } else {
        echo '<p class="fu-meta" style="margin-top:16px;">Ovaj upit nema email adresu.</p>';
    }

    echo '</div>';
}

/* ═══════════════════════════════════════════════
   SETTINGS PAGE (no redirects — handled in admin_init)
═══════════════════════════════════════════════ */
function fu_settings_page() {
    $s = fu_settings();

    $known = [];
    foreach (get_posts(['post_type' => FU_CPT, 'posts_per_page' => -1, 'fields' => 'ids']) as $id) {
        $fid = get_post_meta($id, '_form_id', true);
        if ($fid) $known[$fid] = get_post_meta($id, '_form_name', true) ?: 'Forma #' . $fid;
    }
    foreach ($s['forms'] as $fid => $fd) {
        if (!isset($known[$fid])) $known[$fid] = $fd['label'] ?? 'Forma #' . $fid;
    }

    echo '<div class="wrap">' . fu_style();
    echo '<h1>Postavke — Flotila Upiti</h1>';

    if (!empty($_GET['saved'])) echo '<div class="notice notice-success is-dismissible"><p>Postavke spremljene.</p></div>';

    echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=flotila-upiti-settings')) . '">';
    wp_nonce_field('fu_save_settings');
    echo '<input type="hidden" name="fu_save_settings" value="1">';

    echo '<div class="fu-settings-section"><h2>Općenito</h2>
        <table class="form-table">
            <tr><th>Admin email <em>(prima obavijesti)</em></th>
                <td><input type="email" name="admin_email" value="' . esc_attr($s['admin_email']) . '" class="regular-text"></td></tr>
            <tr><th>Ime pošiljatelja</th>
                <td><input type="text" name="from_name" value="' . esc_attr($s['from_name']) . '" class="regular-text"></td></tr>
            <tr><th>Email pošiljatelja</th>
                <td><input type="email" name="from_email" value="' . esc_attr($s['from_email']) . '" class="regular-text">
                    <p class="description">Email s kojeg se šalju poruke (npr. info@flotila.hr)</p></td></tr>
            <tr><th>Logo URL <em>(u emailovima)</em></th>
                <td><input type="url" name="logo_url" value="' . esc_attr($s['logo_url']) . '" class="large-text" placeholder="https://flotila.hr/wp-content/uploads/logo.png">
                    <p class="description">URL slike loga koja se prikazuje u zaglavlju emailova</p></td></tr>
        </table></div>';

    $imap_test_result = get_transient('fu_imap_test_result');
    if ($imap_test_result !== false) delete_transient('fu_imap_test_result');

    echo '<div class="fu-settings-section" id="imap"><h2>IMAP — čitanje odgovora korisnika</h2>
        <p class="description" style="margin-bottom:14px;">Plugin čita inbox svakih 5 minuta i povlači odgovore korisnika u chat.</p>';

    if ($imap_test_result !== false) {
        $cls = $imap_test_result['ok'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $cls . ' inline" style="margin:0 0 14px;"><p>'
            . esc_html($imap_test_result['msg']) . '</p></div>';
    }

    $test_url = wp_nonce_url(admin_url('admin.php?page=flotila-upiti-settings&imap_test=1'), 'fu_imap_test');

    echo '<table class="form-table">
            <tr><th>IMAP host</th>
                <td><input type="text" name="imap_host" value="' . esc_attr($s['imap_host']) . '" class="regular-text" placeholder="mail.flotila.hr"></td></tr>
            <tr><th>Port</th>
                <td><input type="text" name="imap_port" value="' . esc_attr($s['imap_port']) . '" class="small-text" placeholder="993"></td></tr>
            <tr><th>Korisničko ime</th>
                <td><input type="text" name="imap_user" value="' . esc_attr($s['imap_user']) . '" class="regular-text" placeholder="info@flotila.hr"></td></tr>
            <tr><th>Lozinka</th>
                <td><input type="password" name="imap_pass" value="' . esc_attr($s['imap_pass']) . '" class="regular-text"></td></tr>
            <tr><th>Folder</th>
                <td><input type="text" name="imap_folder" value="' . esc_attr($s['imap_folder']) . '" class="regular-text" placeholder="INBOX"></td></tr>
        </table>
        <p style="margin-top:14px;">
            <a href="' . esc_url($test_url) . '" class="button">Testiraj IMAP vezu ↻</a>
            <span class="description" style="margin-left:10px;">Spremi postavke prije testiranja ako si ih promijenio.</span>
        </p>
    </div>';

    echo '<div class="fu-settings-section"><h2>WhatsApp gumb</h2>
        <table class="form-table">
            <tr><th>Prikaži gumb</th>
                <td><label><input type="checkbox" name="wa_enabled" value="1" ' . checked($s['wa_enabled'], '1', false) . '> Prikaži WhatsApp floating gumb na stranici</label></td></tr>
            <tr><th>Broj telefona</th>
                <td><input type="text" name="wa_number" value="' . esc_attr($s['wa_number']) . '" class="regular-text" placeholder="385915938100">
                    <p class="description">Unesi broj s pozivnim brojem, bez + i razmaka. Npr. <code>385915938100</code></p></td></tr>
        </table>
    </div>';

    echo '<div class="fu-settings-section"><h2>Predlošci emailova po formi</h2>
        <p class="description" style="margin-bottom:14px;">Placeholderi: <code>{ime}</code> <code>{email}</code> <code>{podaci}</code> <code>{datum}</code> <code>{naziv_forme}</code></p>';

    if (empty($known)) {
        echo '<p class="description">Forme će se pojaviti nakon prve zaprimljene prijave.</p>';
    } else {
        foreach ($known as $fid => $fname) {
            $fs = fu_form_settings($fid);
            echo '<div class="fu-form-block">
                <h3>' . esc_html($fname) . ' <small style="color:#999;font-weight:400;">ID: ' . esc_html($fid) . '</small></h3>
                <table class="form-table">
                    <tr><th>Naziv forme</th>
                        <td><input type="text" name="forms[' . esc_attr($fid) . '][label]" value="' . esc_attr($fs['label']) . '" class="regular-text"></td></tr>
                    <tr><th>Naslov notifikacije adminu</th>
                        <td><input type="text" name="forms[' . esc_attr($fid) . '][admin_subject]" value="' . esc_attr($fs['admin_subject']) . '" class="large-text"></td></tr>
                    <tr><th>Naslov potvrde korisniku</th>
                        <td><input type="text" name="forms[' . esc_attr($fid) . '][user_subject]" value="' . esc_attr($fs['user_subject']) . '" class="large-text"></td></tr>
                    <tr><th>Tijelo potvrde korisniku<br><small style="font-weight:400;color:#999;">Podržava HTML</small></th>
                        <td>
                          <textarea name="forms[' . esc_attr($fid) . '][user_body]" rows="16" class="large-text" style="font-family:monospace;font-size:13px;">' . esc_textarea($fs['user_body']) . '</textarea>
                          <p class="description">Placeholderi: <code>{ime}</code> <code>{email}</code> <code>{podaci}</code> <code>{datum}</code> — možeš pisati HTML direktno (inline stilovi preporučeni za email klijente)</p>
                        </td></tr>
                </table></div>';
        }
    }

    echo '</div>';
    echo '<p class="submit"><input type="submit" class="button-primary" value="Spremi postavke"></p>';
    echo '</form></div>';
}

/* ═══════════════════════════════════════════════
   STYLES
═══════════════════════════════════════════════ */
function fu_style(): string {
    return '<style>
        .fu-table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-top:18px;}
        .fu-table th{background:#f8f9fa;padding:11px 14px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#888;border-bottom:2px solid #eee;}
        .fu-table td{padding:11px 14px;border-bottom:1px solid #f2f2f2;font-size:13px;vertical-align:middle;}
        .fu-table tr:last-child td{border-bottom:none;}
        .fu-table tr.fu-row-new td:first-child{border-left:3px solid #1565e8;}
        .fu-table tr:hover td{background:#fafbfc;}
        .fu-badge{padding:3px 8px;border-radius:99px;font-size:11px;font-weight:600;margin-right:3px;}
        .fu-badge-new{background:#dbeafe;color:#1d4ed8;}
        .fu-badge-read{background:#f0f0f0;color:#aaa;}
        .fu-badge-out{background:#fef9c3;color:#854d0e;}
        .fu-badge-in{background:#d1fae5;color:#065f46;}
        .fu-btn{display:inline-block;padding:6px 13px;background:#0f2744;color:#fff!important;border:none;border-radius:6px;cursor:pointer;font-size:12px;text-decoration:none!important;margin-right:4px;line-height:1.6;}
        .fu-btn:hover{background:#1565e8!important;}
        .fu-btn-del{background:#fee2e2!important;color:#b91c1c!important;}
        .fu-btn-del:hover{background:#fca5a5!important;color:#7f1d1d!important;}
        .fu-btn-send{padding:9px 22px;font-size:13px;font-weight:600;}
        .fu-back{display:inline-block;margin-bottom:14px;color:#555;text-decoration:none;font-size:13px;}
        .fu-back:hover{color:#1565e8;}
        .fu-meta{color:#999;font-size:13px;margin:2px 0 0;}
        .fu-meta a{color:#1565e8;}
        .fu-empty{margin-top:30px;color:#999;}
        .fu-chat-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;}
        .fu-chat-wrap{max-width:680px;display:flex;flex-direction:column;gap:10px;margin-bottom:20px;}
        .fu-msg{max-width:82%;padding:11px 15px;border-radius:12px;font-size:14px;line-height:1.6;}
        .fu-msg-in{align-self:flex-start;background:#f1f5f9;border-bottom-left-radius:3px;}
        .fu-msg-out{align-self:flex-end;background:#0f2744;color:#fff;border-bottom-right-radius:3px;}
        .fu-msg-who{font-size:10px;font-weight:700;margin-bottom:4px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;}
        .fu-msg-body{white-space:pre-wrap;word-break:break-word;}
        .fu-reply-box{background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.08);padding:18px 20px;max-width:680px;}
        .fu-form-row{margin-bottom:10px;}
        .fu-form-row label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#888;margin-bottom:4px;}
        .fu-input{width:100%;height:38px;padding:0 11px;border:1px solid #dde3ee;border-radius:7px;font-size:14px;color:#111;box-sizing:border-box;}
        .fu-input:focus,.fu-textarea:focus{border-color:#1565e8;outline:none;box-shadow:0 0 0 3px rgba(21,101,232,.1);}
        .fu-textarea{width:100%;padding:9px 11px;border:1px solid #dde3ee;border-radius:7px;font-size:14px;color:#111;line-height:1.6;resize:vertical;box-sizing:border-box;font-family:inherit;}
        .fu-settings-section{background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.08);padding:20px 24px;max-width:800px;margin-bottom:18px;}
        .fu-settings-section h2{margin:0 0 14px;font-size:15px;border-bottom:1px solid #f0f0f0;padding-bottom:10px;}
        .fu-form-block{border:1px solid #eee;border-radius:8px;padding:14px 16px;margin-bottom:14px;}
        .fu-form-block h3{margin:0 0 10px;font-size:14px;}
        .fu-stats-bar{display:flex;gap:12px;margin:16px 0 4px;flex-wrap:wrap;}
        .fu-stat{background:#fff;border:1px solid #e8eaf0;border-radius:10px;padding:12px 20px;display:flex;flex-direction:column;align-items:center;min-width:110px;}
        .fu-stat-num{font-size:28px;font-weight:700;color:#0f2744;line-height:1;}
        .fu-stat-lbl{font-size:11px;color:#94a3b8;margin-top:3px;text-transform:uppercase;letter-spacing:.4px;}
        .fu-stat-alert .fu-stat-num{color:#1565e8;}
    </style>';
}

/* ═══════════════════════════════════════════════
   WHATSAPP FLOATING BUTTON
═══════════════════════════════════════════════ */
add_action('wp_footer', function () {
    $s = fu_settings();
    if (empty($s['wa_enabled']) || empty($s['wa_number'])) return;
    $number = preg_replace('/\D/', '', $s['wa_number']);
    $url    = 'https://wa.me/' . esc_attr($number);
    ?>
<style>
  .fu-wa-float{
    position:fixed!important;
    left:24px!important;
    bottom:calc(24px + env(safe-area-inset-bottom, 0px))!important;
    z-index:9999!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    width:58px!important;
    height:58px!important;
    border-radius:50%!important;
    background:#25D366!important;
    box-shadow:0 4px 20px rgba(37,211,102,.45)!important;
    text-decoration:none!important;
    transform:none!important;
    transition:box-shadow .2s ease!important;
    -webkit-tap-highlight-color:transparent;
  }
  .fu-wa-float svg{transition:transform .2s ease;transform-origin:center}
  .fu-wa-float:hover{box-shadow:0 6px 28px rgba(37,211,102,.6)!important}
  .fu-wa-float:hover svg{transform:scale(1.1)}
</style>
<a href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer"
   class="fu-wa-float"
   aria-label="Kontaktiraj nas na WhatsAppu">
  <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="#fff" aria-hidden="true" focusable="false">
    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
  </svg>
</a>
<?php });
