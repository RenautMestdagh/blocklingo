<?php
/**
 * Plugin Name:       Blocklingo
 * Plugin URI:        https://github.com/RenautMestdagh/blocklingo
 * Description:       Multilingual WordPress, block by block. Translate pages, templates, headers, footers and menus right in the block editor, with translated URLs and hreflang. Free, no external service.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Renaut Mestdagh
 * Author URI:        https://github.com/RenautMestdagh
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blocklingo
 *
 * @package Blocklingo
 */

defined( 'ABSPATH' ) || exit;

define( 'BLOCKLINGO_VERSION', '0.1.0' );
define( 'BLOCKLINGO_FILE', __FILE__ );
define( 'BLOCKLINGO_DIR', __DIR__ );

require_once __DIR__ . '/includes/class-languages.php';
require_once __DIR__ . '/includes/class-request.php';
require_once __DIR__ . '/includes/class-posts.php';
require_once __DIR__ . '/includes/class-blocks.php';
require_once __DIR__ . '/includes/class-editor.php';
require_once __DIR__ . '/includes/class-links.php';
require_once __DIR__ . '/includes/class-router.php';
require_once __DIR__ . '/includes/class-seo.php';
require_once __DIR__ . '/includes/class-settings-page.php';
require_once __DIR__ . '/includes/class-switcher-block.php';
require_once __DIR__ . '/includes/functions.php';

// The language has to be known before WordPress loads its translations, so right away.
Blocklingo\Request::init();
Blocklingo\Posts::init();
Blocklingo\Blocks::init();
Blocklingo\Editor::init();
Blocklingo\Links::init();
Blocklingo\Router::init();
Blocklingo\Seo::init();
Blocklingo\Settings_Page::init();
Blocklingo\Switcher_Block::init();
