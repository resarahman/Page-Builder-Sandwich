<?php
/**
* Plugin Name: Shortcodeless Columns
* Plugin URI: https://github.com/gambitph/Shortcodeless-Columns
* Description: Create columns striaght in the visual editor without using any shortcodes
* Version: 0.1
* Author: Benjamin Intal - Gambit Technologies Inc
* Author URI: http://gambit.ph
* License: GPL2
* Text Domain: shortcodeless-columns
* Domain Path: /languages
*/


/**
 * Column Shortcodeless Columns Class
 */
class GambitShortcodelessColumns {
	
	// Keep the record of the current column. Used for outputting styles
	private static $columnContainerID = 1;
	

	/**
	 * Hook onto WordPress
	 *
	 * @return	void
	 */
	function __construct() {
		add_action( 'the_content', array( $this, 'cleanOutput' ) );
		add_action( 'admin_init', array( $this, 'addEditorColumnStyles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'columnButtonIcon' ) );
		add_action( 'admin_head', array( $this, 'addColumnButton' ) );
		add_action( 'plugins_loaded', array( $this, 'loadTextDomain' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_custom_wp_admin_style' ) );
	}

	
	/**
	 * Load our translations
	 *
	 * @return	void
	 */
	public function loadTextDomain() {
		load_plugin_textdomain( 'shortcodeless-columns', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
	}

	
	/**
	 * Add the styles for our "table" columns
	 *
	 * @return	void
	 */
	public function addEditorColumnStyles() {
	    add_editor_style( plugins_url( 'css/editor.css', __FILE__ ) );
	}

	
	/**
	 * Add the styles for our column button
	 *
	 * @return	void
	 */
	public function columnButtonIcon() {
	    wp_enqueue_style( 'column-admin', plugins_url( 'css/column-admin.css', __FILE__ ) );
	}
	
	
	/**
	 * Adds our column plugin in TinyMCE
	 *
	 * @param	$pluginArray An array of TinyMCE plugins
	 * @return	An array of TinyMCE plugins
	 */
	public function addTinyMCEPlugin( $pluginArray ) {
	    $pluginArray['scless_column'] = plugins_url( 'js/column-button.js', __FILE__ );
	    return $pluginArray;
	}
	
	
	/**
	 * Registers our column button in TinyMCE
	 *
	 * @param	$buttons array Existing TinyMCE buttons
	 * @return	An array of TinyMCE buttons
	 */
	public function registerTinyMCEButton( $buttons ) {
	   array_push( $buttons, 'scless_column' );
	   return $buttons;
	}
	
	public function load_custom_wp_admin_style() {
		wp_enqueue_script( 'jquery-ui-draggable' );
	}
	
	
	/**
	 * Adds our column button in the TinyMCE visual editor
	 *
	 * @return	void
	 */
	public function addColumnButton() {
	    global $typenow;
	
	    // check user permissions
	    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
		    return;
	    }
	
	    // verify the post type
	    if( ! in_array( $typenow, array( 'post', 'page' ) ) ) {
	        return;
		}
	
	    // check if WYSIWYG is enabled
	    if ( get_user_option( 'rich_editing' ) == 'true' ) {
	        add_filter( 'mce_external_plugins', array( $this, 'addTinyMCEPlugin' ) );
	        add_filter( 'mce_buttons', array( $this, 'registerTinyMCEButton' ) );
			
			$nonSortableElements = 'p,code,blockquote,span,pre,td:not(.scless_column td),th,h1,h2,h3,h4,h5,h6,dt,dd,li,a,address,img,#wp-column-toolbar,.toolbar,.toolbar .dashicons';
			$nonSortableElements = apply_filters( 'sc_non_sortable_elements', $nonSortableElements );
			
			?>
			<script type="text/javascript">
	        var scless_column = {
				dummy_content: '<?php echo addslashes( __( 'Column text', 'default' ) ) ?>',
				modal_title: '<?php echo addslashes( __( 'Columns', 'default' ) ) ?>',
	        	modal_description: '<?php echo addslashes( __( 'Enter a composition here of column ratios separated by spaces.<br>Make sure the ratios sum up to 1.<br>For example: ', 'default' ) ) ?>',
				custom_columns: '<?php echo addslashes( __( 'Custom Columns', 'default' ) ) ?>',
				columns: '<?php echo addslashes( __( '%s Columns', 'default' ) ) ?>',
				change_column: '<?php echo addslashes( __( 'Change Column', 'default' ) ) ?>',
				cancel: '<?php echo addslashes( __( 'Cancel', 'default' ) ) ?>',
				preset: '<?php echo addslashes( __( 'Preset', 'default' ) ) ?>',
				preset_desc: '<?php echo addslashes( __( 'You can change the number of columns below:', 'default' ) ) ?>',
				use_custom: '<?php echo addslashes( __( 'Use custom', 'default' ) ) ?>',
				custom: '<?php echo addslashes( __( 'Custom', 'default' ) ) ?>',
				non_sortable_elements: '<?php echo addslashes( $nonSortableElements ) ?>'
	        };
	        </script>
			<?php
	    }
	}
	
	
	/**
	 * Since we are essentially creating tables in the visual composer, we should convert these tables
	 * into divs for the frontend
	 *
	 * @param	$content string The content being outputted in the frontend
	 * @return	string The modified content
	 */
	public function cleanOutput( $content ) {
		
		// simple_html_dom errors out when we don't have any content
		$contentChecker = trim( $content );
		if ( empty( $contentChecker ) ) {
			return $content;
		}
		
		if ( ! function_exists( 'file_get_html' ) ) {
			require_once( 'inc/simple_html_dom.php' );
		}
	
		wp_enqueue_style( 'shortcodeless_columns', plugins_url( 'css/columns.css', __FILE__ ) );
		
		$columnStyles = '';
	
		$html = str_get_html( $content );

		$tables = $html->find( 'table.scless_column' );
		while ( count( $tables ) > 0 ) {
			$tr = $html->find( 'table.scless_column', 0 )->find( 'tr', 0 );
	
			$newDivs = '<div class="scless_column scless_column_' . self::$columnContainerID . '">';

			foreach ( $tr->children() as $key => $td ) {
				if ( $td->tag != 'td' ) {
					continue;
				}
				
				// Only add in paragraph tags if there aren't any. 
				// This is to ensure that the spacing remains correct.
				$innerHTML = $td->innertext;
				if ( preg_match( '/<p>/', $innerHTML ) !== false ) {
					$innerHTML = '<p>' . $td->innertext . '</p>';
				}
				
				// Gather the column styles
				$columnStyles .= '.scless_column_' . self::$columnContainerID . ' > div:nth-of-type(' . ( $key + 1 ) . ') { ' . esc_attr( $td->style ) . ' }';
			
				$newDivs .= '<div>' . $innerHTML . '</div>';
			}
			$newDivs .= '</div>';
						
			$html->find( 'table.scless_column', 0 )->outertext = $newDivs;
			
			$html = $html->save();
			$html = str_get_html( $html );
		
			$tables = $html->find( 'table.scless_column' );
			
			self::$columnContainerID++;
		}
	
		return '<style id="scless_column">' . $columnStyles . '</style>' . $html;
	}

}
new GambitShortcodelessColumns();