<?php
// Helper wrapper so admin/tools code can call the repair logic without
// needing to know the class name.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'fdm_repair_competition_codes' ) ) {
    /**
     * Global helper to trigger competition code repair from admin tools.
     *
     * @param int $limit Max number of matches to repair in one run
     * @return array Stats from FDM_E_Datasource_V2::repair_competition_codes()
     */
    function fdm_repair_competition_codes( $limit = 500 ) {
        if ( ! class_exists( 'FDM_E_Datasource_V2' ) ) {
            require_once FDM_PLUGIN_DIR . 'includes/e_datasource_v2.php';
        }
        return FDM_E_Datasource_V2::repair_competition_codes( $limit );
    }
}


