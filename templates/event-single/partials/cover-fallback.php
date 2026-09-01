<?php
if (!defined('ABSPATH')) {
    exit;
}

if ($cover_url === '' || $cover_thumb !== '') {
    return;
}
?>
<figure class="mj-member-event-single__card" style="padding:0;overflow:hidden;">
    <img src="<?php echo esc_url($cover_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" style="display:block;width:100%;height:auto;" />
</figure>
