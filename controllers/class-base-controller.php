<?php
/**
 * The base controller.
 *
 * @author Daryl Lozupone <daryl@actionhook.com>
 * @since WPMVCBase 0.1
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( ! class_exists( 'Base_Controller' ) ):
	/**
	 * The base controller.
	 *
	 * @package WPMVCBase\Controllers
	 * @abstract
	 * @version 0.1
	 * @since WP_Base 0.3
	 */
	class Base_Controller
	{
		/**
		 * The absolute path to the main plugin file. Ends with a slash.
		 * 
		 * @var   string
		 * @since 0.3
		 */
		protected $_main_plugin_file;
		
		/**
		 * The absolute path to the plugin app path. Ends with a slash.
		 * 
		 * @var   string
		 * @since 0.3
		 */
		protected $_app_path;
		
		/**
		 * The absolute path to the WPMVC Base path. Ends with a slash.
		 * 
		 * @var   string
		 * @since 0.3
		 */
		protected $_base_path;
		
		/**
		 * The uri to the plugin directory. Ends with a slash.
		 *
		 * @var   string
		 * @since 0.3
		 */
		protected $_uri;
		
		/**
		 * The plugin text domain.
		 *
		 * @var   string
		 * @since 0.3
		 */
		protected $_txtdomain;
		
		/**
		 * The class constructor
		 *
		 * @since 0.1
		 */
		public function __construct( $main_plugin_file, $app_path, $base_path, $uri, $txtdomain )
		{
			$this->_main_plugin_file = $main_plugin_file;
			$this->_app_path         = trailingslashit( $app_path );
			$this->_base_path        = trailingslashit( $base_path );
			$this->_uri              = trailingslashit( $uri );
			$this->_txtdomain        = $txtdomain;
			
			add_action( 'wp_enqueue_scripts',    array( &$this, 'wp_enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
			add_action( 'add_meta_boxes',        array( &$this, 'add_meta_boxes' ) );
		}
		
		/**
		 * Add shortcodes to WP.
		 *
		 * @param array $shortcodes
		 * @return void
		 * @since WPMVCBase 0.1
		 */
		public function add_shortcodes( array $shortcodes )
		{
			if ( ! is_array( $shortcodes ) ) {
				return new WP_Error(
					'non-array',
					sprintf( __( '%s::%s expects an array', 'wpmvcb' ), __CLASS__, __FUNCTION__ ),
					$shortcodes
				);
			}
			
			foreach ( $shortcodes as $key => $shortcode ) {
				add_shortcode( $key, $shortcode );
			}
		}

		/**
		 * The WP add_meta_boxes action callback
		 *
		 * @param array $metaboxes Array containing Base_Model_Metabox objects.
		 * @internal
		 * @access public
		 * @since 0.1
		 * @see Base_Model_Metabox
		 */
		public function add_meta_boxes( array $metaboxes )
		{
			global $post;

			foreach ( $metaboxes as $metabox ) {
				foreach( $metabox->get_post_types() as $post_type ) {
					add_meta_box( 
						$metabox->get_id(),
						$metabox->get_title(),
						is_callable( $metabox->get_callback() ) ? $metabox->get_callback() : array( &$this, 'render_metabox' ),
						$post_type,
						$metabox->get_context(),
						$metabox->get_priority(),
						$metabox->get_callback_args()
					);
				}
			}
		}
		
		/**
		 * Enqueue scripts.
		 *
		 * @uses wp_enqueue_script
		 * @param array $scripts Array containing Base_Model_JS objects
		 * @return void|object WP_Error object on failure.
		 * @since WPMVCBase 0.3
		 */
		public function enqueue_scripts( array $scripts )
		{
			
			if ( ! is_array( $scripts ) ) {
				return new WP_Error(
					'non-array',
					sprintf( __( '%s::%s expects an array', 'wpmvcb' ), __CLASS__, __FUNCTION__ ),
					$scripts
				);
			}
			
			foreach ( $scripts as $key => $script ) {
				if( is_a( $script, 'Base_Model_JS_Object' ) ) {
					wp_enqueue_script(
						$script->get_handle(),
						$script->get_src(),
						$script->get_deps(),
						$script->get_ver(),
						$script->get_in_footer()
					);
				}
				
				if( ! is_a( $script, 'Base_Model_JS_Object' ) ) {
					if( ! isset( $wp_error ) ) {
						$wp_error = new WP_Error();
					}
					
					$wp_error->add(
						'invalid object type',
						sprintf( __( '%s is not a Base_Model_JS_Object', 'wpmvcbase' ), $key ),
						$script
					);
				}
			}
			
			//return the error object for invalid script types
			if( isset( $wp_error ) ) {
				return $wp_error;
			}
		}
		
		/**
		 * Render a metabox.
		 *
		 * This function serves as the callback for a metabox.
		 *
		 * @param object $post The WP post object.
		 * @param object $metabox The WP_Metabox object to be rendered.
		 * @param string $txtdomain The plugin text domain.
		 * @param string $nonce_action The plugin nonce action.
		 * @param string $nonce_name The plugin nonce name.
		 * @internal
		 * @access public
		 * @todo move the filter into the add function
		 * @since 0.1
		 */
		public function render_metabox( $post, $metabox )
		{
			//get elements required for this particular view
			$metabox = apply_filters( 'filter_metabox_callback_args', $metabox, $post );

			//add the uri
			$metabox['args']['uri'] = $this->uri;

			if ( isset( $this->nonce_action ) && isset( $this->nonce_name ) ):
				//generate a nonce
				$nonce = wp_nonce_field( $this->nonce_action, $this->nonce_name, true, false );
			endif;

			if ( isset( $this->txtdomain ) ):
				$txtdomain = $this->txtdomain;
			else :
				$txtdomain = '';
			endif;

			//Is a view file specified for this metabox?
			if ( isset( $metabox['args']['view'] ) ) :
				if ( file_exists( $metabox['args']['view'] ) ) :
					//require the appropriate view for this metabox
					include_once( $metabox['args']['view'] );
				else :
					trigger_error(
						sprintf(
							__( 'The view file %s for metabox id %s does not exist', $this->txtdomain ),
							$metabox['args']['view'],
							$metabox['id']
						),
						E_USER_WARNING
					);
				endif;
			else :
				trigger_error(
					sprintf(
						__( 'No view specified in the callback arguments for metabox id %s', $this->txtdomain ),
						$metabox['id']
					),
					E_USER_WARNING
				);
			endif;
		}
		
		/**
		 * WP save_post action authenticator.
		 *
		 * @param string $post_id The WP post id.
		 * @param string $post_type The post type.
		 * @param object $post_data The POSTed data.
		 * @internal
		 * @access public
		 * @since 0.1
		 */
		public function authenticate_post( $post_id, $post_type, $post_data, $nonce_name, $nonce_action )
		{

			// verify if this is an auto save routine.
			// If it is our form has not been submitted, so we dont want to do anything
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			// We need to check if the current user is authorised to do this action.
			switch ( $post_type ) {
				case 'page':
					if ( ! current_user_can( 'edit_page', $post_id ) ) {
						return;
					}
				default:
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return;
					}
			}

			// Third we need to check if the user intended to change this value.
			if ( ! isset( $post_data[ $nonce_name ] ) || ! wp_verify_nonce( $post_data[ $nonce_name ], $nonce_action ) ) {
				return;
			}

			return true;
		}
	}
endif;
