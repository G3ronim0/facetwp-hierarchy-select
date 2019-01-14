<?php

class FacetWP_Facet_Hierarchy_Select_Addon extends FacetWP_Facet
{

    function __construct() {
        $this->label = __( 'Hierarchy Select', 'facetwp-hierarchy-select' );
        $this->i18n  = array(
          'Depth' => __( 'Depth', 'facetwp-hierarchy-select' ),
          'label' => __( 'label', 'facetwp-hierarchy-select' )
        );
    }


    /**
     * Load the available choices
     */
    function load_values( $params ) {
        global $wpdb;

        $facet = $params['facet'];
        $from_clause = $wpdb->prefix . 'facetwp_index f';
        $where_clause = $this->get_where_clause( $facet );

        // Orderby
        $orderby = $this->get_orderby( $facet );

        $orderby = apply_filters( 'facetwp_facet_orderby', $orderby, $facet );
        $from_clause = apply_filters( 'facetwp_facet_from', $from_clause, $facet );
        $where_clause = apply_filters( 'facetwp_facet_where', $where_clause, $facet );

        $sql = "
        SELECT f.post_id, f.facet_value, f.facet_display_value, f.term_id, f.parent_id, f.depth, COUNT(DISTINCT f.post_id) AS counter
        FROM $from_clause
        WHERE f.facet_name = '{$facet['name']}' $where_clause
        GROUP BY f.facet_value
        ORDER BY $orderby";

        return $wpdb->get_results( $sql, ARRAY_A );
    }


    /**
     * Filter out irrelevant choices
     */
    function filter_load_values( $values, $selected_values ) {
        foreach ( $selected_values as $depth => $selected_value ) {
            $selected_id = -1;

            foreach ( $values as $key => $val ) {
                if ( $selected_value == $val['facet_value'] ) { // save the parent_id
                    $selected_id = $val['term_id'];
                }

                if ( $val['depth'] == ( $depth + 1 ) ) { // child of the selected value
                    if ( $val['parent_id'] != $selected_id ) {
                        unset( $values[ $key ] );
                    }
                }
            }
        }

        return $values;
    }


    /**
     * Generate the facet HTML
     */
    function render( $params ) {

        $output = '';
        $facet = $params['facet'];
        $values = (array) $params['values'];
        $selected_values = (array) $params['selected_values'];

        // Filter out irrelevant choices
        $values = $this->filter_load_values( $values, $selected_values );

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
                $class = empty( $disabled ) ? '' : ' is-disabled';
                $label = empty( $levels[ $level ] ) ? __( 'Any', 'facetwp-hierarchy-select' ) : $levels[ $level ];
                $label = facetwp_i18n( $label );
                $output .= '<select class="facetwp-hierarchy_select' . $class . '" data-level="' . $level . '"' . $disabled . '>';
                $output .= '<option value="">' . esc_attr( $label ) . '</option>';
            }

            if ( $level <= $num_active_levels ) {
                $selected = in_array( $result['facet_value'], $selected_values ) ? ' selected' : '';

                // Determine whether to show counts
                $display_value = esc_attr( $result['facet_display_value'] );
                $show_counts = apply_filters( 'facetwp_facet_dropdown_show_counts', true, array( 'facet' => $facet ) );

                if ( $show_counts ) {
                    $display_value .= ' (' . number_format_i18n( $result['counter'] ) . ')';
                }

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
     * Output any front-end scripts
     */
    function front_scripts() {
        FWP()->display->assets['hierarchy-select-front.js'] = plugins_url( '', __FILE__ ) . '/assets/js/front.js';
    }


    /**
     * Output any admin scripts
     */
    function admin_scripts() {
?>
<script>

window.FWPHT = {
    __: function(str) {
        return ('undefined' !== typeof FWPHT.i18n[str]) ? FWPHT.i18n[str] : str;
    },
    i18n: <?php echo json_encode( $this->i18n ); ?>,
};

Vue.component('levels', {
    props: ['facet'],
    template: `
    <div>
        <div v-for="(label, index) in facet.levels">
            <div style="padding-bottom:10px">
                <input type="text" v-model="facet.levels[index]"  v-bind:placeholder="getPlaceholder(index)" />
                <button @click="removeLabel(index)">x</button>
            </div>
        </div>
        <button @click="addLabel()"><?php _e( 'Add label', 'facetwp-hierarchy-select' ); ?></button>
    </div>
    `,
    methods: {
        addLabel: function() {
            this.facet.levels.push('');
        },
        removeLabel: function(index) {
            Vue.delete(this.facet.levels, index);
        },
        getPlaceholder: function(index) {
            return FWPHT.__('Depth') + ' ' + index + ' ' + FWPHT.__('label');
        }
    }
});

</script>
<?php
    }


    /**
    * Output admin settings HTML
    */
    function settings_html() {
    ?>
        <div class="facetwp-row">
            <div class="facetwp-col"><?php _e( 'Sort by', 'facetwp-hierarchy-select' ); ?>:</div>
            <div class="facetwp-col">
                <select class="facet-orderby">
                    <option value="count"><?php _e( 'Highest Count', 'facetwp-hierarchy-select' ); ?></option>
                    <option value="display_value"><?php _e( 'Display Value', 'facetwp-hierarchy-select' ); ?></option>
                    <option value="raw_value"><?php _e( 'Raw Value', 'facetwp-hierarchy-select' ); ?></option>
                </select>
                <input type="hidden" class="facet-hierarchical" value="yes" />
                <input type="hidden" class="facet-levels" value="[]" />
            </div>
        </div>
        <div class="facetwp-row">
            <div class="facetwp-col">
                <?php _e( 'Depth labels', 'facetwp-hierarchy-select' ); ?></span>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content">
                        <?php _e( 'Enter a label for each depth level', 'facetwp-hierarchy-select' ); ?>
                    </div>
                </div>
            </div>
            <div class="facetwp-col">
                <levels :facet="facet"></levels>
            </div>
        </div>
    <?php
    }
}
