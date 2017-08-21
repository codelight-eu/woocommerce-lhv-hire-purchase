<?php
/**
 * The template for Wordpress admin notices
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>

<div class="notice notice-<?= $type ?>">
    <p>
        <?= $message ?>
    </p>
</div>
