<div class="wrap">
    <h2><?php _e('Tax Service Report', 'tax-service'); ?> </h2>
    <form method="post">
        <input type="hidden" name="page" value="ttest_list_table">
        <?php
        $list_table = new ReportTable();
        $list_table->prepare_items();
        $list_table->search_box( 'search', 'search' );
        $list_table->display();
        ?>
    </form>
</div>

