<?php
/*
Plugin Name: FacetWP - Hierarchy Select
Description: Hierarchy select facet type
Version: 0.1
Author: FacetWP, LLC
Author URI: https://facetwp.com/
GitHub URI: facetwp/facetwp-hierarchy-select
*/

defined( 'ABSPATH' ) or exit;


/**
 * FacetWP registration hook
 */
function fwp_hierarchy_select_facet( $facet_types ) {
    $facet_types['hierarchy_select'] = new FacetWP_Facet_Hierarchy_Select();
    return $facet_types;
}
add_filter( 'facetwp_facet_types', 'fwp_hierarchy_select_facet' );


/**
 * Hierarchy Select facet class
 */
class FacetWP_Facet_Hierarchy_Select
{

    function __construct() {
        $this->label = __( 'Hierarchy Select', 'fwp' );

        add_filter( 'facetwp_store_unfiltered_post_ids', array( $this, 'store_unfiltered_post_ids' ) );
    }


    /**
     * Load the available choices
     */
    function load_values( $params ) {
        global $wpdb;

        $facet = $params['facet'];
        $from_clause = $wpdb->prefix . 'facetwp_index f';
        $where_clause = $params['where_clause'];

        // Orderby
        $orderby = 'counter DESC, f.facet_display_value ASC';
        if ( 'display_value' == $facet['orderby'] ) {
            $orderby = 'f.facet_display_value ASC';
        }
        elseif ( 'raw_value' == $facet['orderby'] ) {
            $orderby = 'f.facet_value ASC';
        }

        // Sort by depth just in case
        $orderby = "f.depth, $orderby";

        /*
        // Properly handle "OR" facets
        if ( 'or' == $facet['operator'] ) {

            // Apply filtering (ignore the facet's current selections)
            if ( isset( FWP()->or_values ) && ( 1 < count( FWP()->or_values ) || ! isset( FWP()->or_values[ $facet['name'] ] ) ) ) {
                $post_ids = array();
                $or_values = FWP()->or_values; // Preserve the original
                unset( $or_values[ $facet['name'] ] );

                $counter = 0;
                foreach ( $or_values as $name => $vals ) {
                    $post_ids = ( 0 == $counter ) ? $vals : array_intersect( $post_ids, $vals );
                    $counter++;
                }

                // Return only applicable results
                $post_ids = array_intersect( $post_ids, FWP()->unfiltered_post_ids );
            }
            else {
                $post_ids = FWP()->unfiltered_post_ids;
            }

            $post_ids = empty( $post_ids ) ? array( 0 ) : $post_ids;
            $where_clause = ' AND post_id IN (' . implode( ',', $post_ids ) . ')';
        }
        */

        $orderby = apply_filters( 'facetwp_facet_orderby', $orderby, $facet );
        $from_clause = apply_filters( 'facetwp_facet_from', $from_clause, $facet );
        $where_clause = apply_filters( 'facetwp_facet_where', $where_clause, $facet );

        $sql = "
        SELECT f.facet_value, f.facet_display_value, f.term_id, f.parent_id, f.depth, COUNT(DISTINCT f.post_id) AS counter
        FROM $from_clause
        WHERE f.facet_name = '{$facet['name']}' $where_clause
        GROUP BY f.facet_value
        ORDER BY $orderby";

        $output = $wpdb->get_results( $sql, ARRAY_A );

        return $output;
    }


    /**
     * Generate the facet HTML
     */
    function render( $params ) {

        $output = '';
        $facet = $params['facet'];
        $values = (array) $params['values'];
        $selected_values = (array) $params['selected_values'];

        $num_active_levels = count( $selected_values );
        $levels = isset( $facet['levels'] ) ? (array) $facet['levels'] : array();
        $prev_level = -1;

        foreach ( $values as $index => $result ) {
            $level = (int) $result['depth'];

            if ( $level != $prev_level ) {
                if ( 0 < $index ) {
                    $output .= '</select>';
                }

                $disabled = ( $level <= $num_active_levels ) ? '' : ' disabled';
                $label = empty( $levels[ $level ] ) ? __( 'Any', 'fwp' ) : $levels[ $level ];
                $output .= '<select class="facetwp-hierarchy_select" data-level="' . $level . '"' . $disabled . '>';
                $output .= '<option value="">' . esc_attr( $label ) . '</option>';
            }

            if ( $level <= $num_active_levels ) {
                $selected = in_array( $result['facet_value'], $selected_values ) ? ' selected' : '';

                // Determine whether to show counts
                $display_value = esc_attr( $result['facet_display_value'] );
                $show_counts = apply_filters( 'facetwp_facet_dropdown_show_counts', true, array( 'facet' => $facet ) );

                $output .= '<option value="' . esc_attr( $result['facet_value'] ) . '"' . $selected . '>' . $display_value . '</option>';
            }

            $prev_level = $level;
        }

        if ( -1 < $prev_level ) {
            $output .= '</select>';
        }

        return $output;
    }


    /**
     * Filter the query based on selected values
     */
    function filter_posts( $params ) {
        global $wpdb;

        $facet = $params['facet'];
        $selected_values = (array) $params['selected_values'];
        $selected_values = array_pop( $selected_values );

        $sql = "
        SELECT DISTINCT post_id FROM {$wpdb->prefix}facetwp_index
        WHERE facet_name = '{$facet['name']}' AND facet_value IN ('$selected_values')";
        return $wpdb->get_col( $sql );
    }


    /**
     * Output any admin scripts
     */
    function admin_scripts() {
?>
<script>
(function($) {
    wp.hooks.addAction('facetwp/load/hierarchy_select', function($this, obj) {
        $this.find('.facet-source').val(obj.source);
        $this.find('.facet-display-type').val(obj.display_type);
        $this.find('.facet-parent-term').val(obj.parent_term);
        $this.find('.facet-orderby').val(obj.orderby);
        var wrapper = $this.find('.hierarchy-add-level-wrapper');
        for (var l = 0; l < obj.levels.length; l++) {
            var level = create_level(obj.levels[l]);
            level.insertBefore(wrapper);
        }
    });

    wp.hooks.addFilter('facetwp/save/hierarchy_select', function($this, obj) {
        obj['hierarchical'] = 'yes'; // locked.
        obj['source'] = $this.find('.facet-source').val();
        obj['display_type'] = $this.find('.facet-display-type').val();
        obj['parent_term'] = $this.find('.facet-parent-term').val();
        obj['orderby'] = $this.find('.facet-orderby').val();
        obj['levels'] = [];
        $this.find('.facet-label-level').each(function () {
            obj['levels'].push(this.value);
        });

        return obj;
    });

    function create_level(val) {
        var template = $('#hierarchy-select-tmpl').html();
        var $new_line = $(template);

        if (val) {
            $new_line.find('.facet-label-level').val(val);
        }

        return $new_line;
    }

    $(document).on('click', '.hierarchy-add-level', function(e) {
        var clicked = $(this);
        var parent = clicked.closest('.hierarchy-add-level-wrapper');
        var new_line = create_level();
        new_line.insertBefore(parent);
    });

    $(document).on('click', '.hierarchy-select-remove-level', function(e) {
        $(this).closest('.hierarchy-select-level').remove();
    });
})(jQuery);
</script>
<script type="text/html" id="hierarchy-select-tmpl">
    <tr class="hierarchy-select-level">
        <td>
            <?php _e( "Depth {n} label", 'fwp' ); ?>:
            <div class="facetwp-tooltip">
                <span class="icon-question">?</span>
                <div class="facetwp-tooltip-content">
                    Customize this level's label.
                </div>
            </div>
        </td>
        <td>
            <input type="text" class="facet-label-level" value="<?php esc_attr_e( 'Any', 'fwp' ); ?>" />
            <input type="button" class="button button-small hierarchy-select-remove-level" style="margin: 1px;" value="<?php esc_attr_e( 'Remove', 'fwp' ); ?>" />
        </td>
    </tr>
</script>
<?php
    }


    /**
     * Output any front-end scripts
     */
    function front_scripts() {
?>
<script>
(function($) {
    wp.hooks.addAction('facetwp/refresh/hierarchy_select', function($this, facet_name) {
        var selected_values = [];
        $this.find('.facetwp-hierarchy_select option:selected').each(function () {
            var value = $(this).attr('value');
            if (value.length) {
                selected_values.push($(this).attr('value'));
            }
        });
        FWP.facets[facet_name] = selected_values;
    });

    wp.hooks.addFilter('facetwp/selections/hierarchy_select', function(output, params) {
        var selected_values = [];
        params.el.find('.facetwp-hierarchy_select option:selected').each(function(i) {
            var value = $(this).attr('value');
            if (value.length) {
                selected_values.push({ value: value, label: $(this).text() });
            }
        });
        return selected_values;
    });

    $(document).on('change', '.facetwp-type-hierarchy_select select', function() {
        var $this = $(this);
        var $parent = $this.closest('.facetwp-facet');
        var active_level = parseInt( $this.attr('data-level') );
        $parent.find('select').each(function(idx, el) {
            var level = parseInt( $(el).attr('data-level') );
            if (level > active_level) {
                $(el).val('');
            }
        });

        FWP.autoload();
    });
})(jQuery);
</script>
<?php
    }


    /**
    * Output admin settings HTML
    */
    function settings_html() {
    ?>
        <tr>
            <td>
                <?php _e( 'Parent term', 'fwp' ); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content">
                        Enter the parent term's ID if you want to a custom starting level.
                        Otherwise, leave blank.
                    </div>
                </div>
            </td>
            <td>
                <input type="text" class="facet-parent-term" value=""/>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'Sort by', 'fwp' ); ?>:</td>
            <td>
                <select class="facet-orderby">
                    <option value="count"><?php _e( 'Highest Count', 'fwp' ); ?></option>
                    <option value="display_value"><?php _e( 'Display Value', 'fwp' ); ?></option>
                    <option value="raw_value"><?php _e( 'Raw Value', 'fwp' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'Display Type', 'fwp' ); ?>:</td>
            <td>
                <select class="facet-display-type">
                    <option value="active"><?php _e( 'Show active dropdowns', 'fwp' ); ?></option>
                    <option value="all"><?php _e( 'Show all dropdowns', 'fwp' ); ?></option>
                </select>
            </td>
        </tr>
        <tr class="hierarchy-add-level-wrapper">
            <td></td>
            <td>
                <input type="button" class="hierarchy-add-level button button-small" style="width: 200px;" value="<?php _e( 'Add Label', 'fwp' ); ?>" />
            </td>
        </tr>
    <?php
    }


    /**
     * Store unfiltered post IDs if this facet type exists
     */
    /*
    function store_unfiltered_post_ids( $boolean ) {
        if ( FWP()->helper->facet_setting_exists( 'type', 'hierarchy_select' ) ) {
            return true;
        }

        return $boolean;
    }
    */
}
