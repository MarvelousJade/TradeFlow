<?php get_header(); ?>
<div class="tf-content">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article <?php post_class(); ?>>
            <h1><?php the_title(); ?></h1>
            <div class="entry-content"><?php the_content(); ?></div>
        </article>
    <?php endwhile; else : ?>
        <h1><?php esc_html_e('Nothing here yet.', 'tradeflow'); ?></h1>
    <?php endif; ?>
</div>
<?php get_footer(); ?>

