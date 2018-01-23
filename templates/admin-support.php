<?php

/**
 * The template for support section located in WooCommerce > Settings > Checkout > LHV hire-purchase
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>

<hr>

<h4>
    <?= __('Having trouble? Check out the documentation or get in touch - our support is happy to help!', 'lhv-hire-purchase'); ?>
</h4>

<a class="button button-lhv-docs" href="<?= $documentationUrl; ?>" target="_blank" >
    <?= __('Read the docs', 'lhv-hire-purchase'); ?>
</a>
<a class="button button-lhv-support" href="<?= $supportUrl; ?>" target="_blank" >
    <?= __('Contact support', 'lhv-hire-purchase'); ?>
</a>
<br><br>

<hr>
