<?php
/**
 * Plugin Name: Mudlet Snapshots
 * Plugin URI:  https://make.mudlet.org/snapshots/
 * Description: Provides Tools for managing Mudlet Snapshots.
 * Version:     20190701
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') or exit();



$mudletsnaps_config = array();
$mudletsnaps_config['snapshot_root'] = '';
$mudletsnaps_config['snapshot_files'] = 'files/';
$mudletsnaps_config['db_host'] = '';
$mudletsnaps_config['db_name'] = '';
$mudletsnaps_config['db_user'] = '';
$mudletsnaps_config['db_pass'] = '';
$mudletsnaps_config['db_charset'] = 'utf8mb4';


if ( ! class_exists( 'MudletSnapshots_WP_List_Table' ) ) {
    $dir = dirname(__FILE__);
	require_once($dir.'/class-ms-wp-list-table.php');
}

class MudletSnapsUsers_ListTable extends MudletSnapshots_WP_List_Table {
	public function __construct() {
		parent::__construct([
			'singular' => 'User',
			'plural'   => 'Users',
			'ajax'     => false
		]);
	}
    
    public static function get_users($per_page=10, $page_number=1) {
        $sql = "SELECT `id`, `name` FROM `Users`";
        if( isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby'])) {
            $orderbys=array('id','name');
            $key1=array_search($_REQUEST['orderby'],$orderbys);
            $orderby=$orderbys[$key1];
        
            $sql .= ' ORDER BY '. $orderby;
            if( isset($_REQUEST['order']) && !empty($_REQUEST['order']) ) {
                $orders=array('asc','desc');
                $key2=array_search($_REQUEST['order'],$orders);
                $order=$orders[$key2];
        
                $sql .= ' '. strtoupper($order);
            }
        }
        $sql .= " LIMIT ". $per_page;
        $sql .= ' OFFSET '. ($page_number - 1) * $per_page;
        
        $dbh = mudletsnaps_getSnapshotPDO_DB();
        if( $dbh == false ) {
            echo("Error Connecting with Snapshot Database!");
            return false;
        } else {
            $stmt = $dbh->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $data;
        }
    }

    public static function delete_user($id) {
        $dbh = mudletsnaps_getSnapshotPDO_DB();
        if( $dbh == false ) {
            echo("Error Connecting with Snapshot Database!");
        } else {
            $stmt = $dbh->prepare("DELETE FROM `Users` WHERE `Users`.`id`=:uid");
            $stmt->bindParam(':uid', $id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
    
    public static function record_count() {
        $dbh = mudletsnaps_getSnapshotPDO_DB();
        if( $dbh == false ) {
            echo("Error Connecting with Snapshot Database!");
            return 0;
        } else {
            $stmt = $dbh->query("SELECT COUNT(*) FROM `Users`");
            $res = $stmt->fetch();
            return $res[0];
        }
    }
    
    public function no_items() {
        _e('No Users available.', 'mudletsnaps');
    }
    
    function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
		);
	}
    
    public function column_name( $item ) {
        $delete_nonce = wp_create_nonce( 'mudletsnaps_delete_user' );
        
        $title = '<strong>' . $item['name'] . '</strong>';
        $actions = [
            'edit' => sprintf('<a href="?page=%s&action=edituser&user=%s">Edit</a>', esc_attr($_REQUEST['page']), absint($item['id'])),
            'delete' => sprintf('<a href="?page=%s&action=delete&user=%s&_wpnonce=%s">Delete</a>', esc_attr($_REQUEST['page']), absint($item['id']), $delete_nonce)
        ];
        return $title . $this->row_actions( $actions );
    }
    
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
            case 'name':
                return $item[ $column_name ];
            default:
                return print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }
    
    function get_columns() {
        $columns = [
            'cb'    => '<input type="checkbox" />',
            'id'    => 'ID',
            'name'  => 'Name'
        ];
        return $columns;
    }
    
    public function get_sortable_columns() {
        $sortable_columns = array(
            'name' => array('name', true),
            'id' => array('id', false)
        );
        return $sortable_columns;
    }
    
    public function get_column_info() {
        $cols = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        return array($cols, $hidden, $sortable);
    }
    
    public function get_bulk_actions() {
        $actions = [
            'bulk-delete' => 'Delete'
        ];
        return $actions;
    }
    
    public function prepare_items() {
        $this->_column_headers = $this->get_column_info();
        
        $per_page     = $this->get_items_per_page('users_per_page', 5);
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();
        
        $this->set_pagination_args( [
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page'    => $per_page //WE have to determine how many items to show on a page
        ] );
        
        $this->items = self::get_users( $per_page, $current_page );
    }
    
    public function process_bulk_action() {
        if ( 'delete' === $this->current_action() ) {
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );
            
            if ( ! wp_verify_nonce( $nonce, 'mudletsnaps_delete_user' ) ) {
                exit( 'LOOK AT ALL THESE CHICKENS!' );
            }
            else {
                self::delete_user( absint( $_GET['user'] ) );
                wp_redirect( "?page=mudlet-snapshots" );
                exit();
            }
        }
        
        if( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
           || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' ) ) 
        {
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );
            if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
                exit( 'LOOK AT ALL THESE CHICKENS!' );
            }
            
            if( !isset($_POST['bulk-delete']) ) {
                wp_redirect("?page=mudlet-snapshots");
                exit();
            }
            
            foreach( $_POST['bulk-delete'] as $id) {
                $id = absint($id);
                self::delete_user( $id );
            }
            wp_redirect( "?page=mudlet-snapshots" );
            exit();
        }
    }
}


$mudletsnaps_nonce_calls = 0;
function mudletsnaps_nonce_field($action) {
    global $mudletsnaps_nonce_calls;
    $mudletsnaps_nonce_calls = $mudletsnaps_nonce_calls + 1;
    $nonce_calls = strval($mudletsnaps_nonce_calls);
    $field = wp_nonce_field($action, '_wpnonce', true, false);
    $find  = 'id="_wpnonce"';
    $repl  = 'id="_wpnonce_'.$nonce_calls.'"';
    $field = str_replace($find, $repl, $field);
    
    echo $field;
}

function mudletsnaps_getMaxCapacityConfig() {
    global $mudletsnaps_config;
    $confFile = $mudletsnaps_config['snapshot_root'] . 'config.php';
    if (is_file($confFile)) {
        $content = file_get_contents($confFile);
        preg_match('/define\(\'MAX_CAPACITY_BYTES\', ([0-9]+)\);/i', $content, $m);
        
        if (isset($m[1])) {
            echo mudletsnaps_human_filesize(intval($m[1]));
        } else {
            echo 'Unknown!';
        }
    } else {
        echo 'Unknown';
    }
}

function mudletsnaps_getSnapshotPDO_DB() {
    global $mudletsnaps_config;
    try {
        $dsn = 'mysql: host='. $mudletsnaps_config['db_host'] .';dbname='. $mudletsnaps_config['db_name'] .';charset='. $mudletsnaps_config['db_charset'];
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES '. $mudletsnaps_config['db_charset'],
        );
        $dbh = new PDO($dsn, $mudletsnaps_config['db_user'], $mudletsnaps_config['db_pass'], $options);
        $dsn = null;
        
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        return $dbh;
    } 
    catch (PDOException $e) {
        return false;
    }
}

function mudletsnaps_getUserByName($name) {
    $dbh = mudletsnaps_getSnapshotPDO_DB();
    if( $dbh == false ) {
        echo("Error Connecting with Snapshot Database!");
        return false;
    } else {
        $stmt = $dbh->prepare("SELECT `id`, `name` FROM `Users` WHERE `name`=:name");
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $r = $stmt->execute();
        if( $r ) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            return false;
        }
    }
}

function mudletsnaps_getUserById($id) {
    $dbh = mudletsnaps_getSnapshotPDO_DB();
    if( $dbh == false ) {
        echo("Error Connecting with Snapshot Database!");
        return false;
    } else {
        $stmt = $dbh->prepare("SELECT `id`, `name` FROM `Users` WHERE `id`=:uid;");
        $stmt->bindParam(':uid', $id, PDO::PARAM_INT);
        $r = $stmt->execute();
        if( $r ) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            return false;
        }
    }
}

function mudletsnaps_addUser($name, $hash) {
    $dbh = mudletsnaps_getSnapshotPDO_DB();
    if( $dbh == false ) {
        echo("Error Connecting with Snapshot Database!");
        return false;
    } else {
        $stmt = $dbh->prepare("INSERT INTO `Users` (`name`, `phash`) VALUES (:name, :phash);");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':phash', $hash);
        
        return $stmt->execute();
    }
}

function mudletsnaps_editUserName($id, $name) {
    $dbh = mudletsnaps_getSnapshotPDO_DB();
    if( $dbh == false ) {
        echo("Error Connecting with Snapshot Database!");
        return false;
    } else {
        $stmt = $dbh->prepare("UPDATE `Users` SET `name`=:name WHERE `id`=:uid;");
        $stmt->bindParam(':uid', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        return $stmt->execute();
    }
}

function mudletsnaps_editUserPass($id, $hash) {
    $dbh = mudletsnaps_getSnapshotPDO_DB();
    if( $dbh == false ) {
        echo("Error Connecting with Snapshot Database!");
        return false;
    } else {
        $stmt = $dbh->prepare("UPDATE `Users` SET `phash`=:phash WHERE `id`=:uid;");
        $stmt->bindParam(':uid', $id, PDO::PARAM_INT);
        $stmt->bindParam(':phash', $hash, PDO::PARAM_STR);
        return $stmt->execute();
    }
}

function mudletsnaps_human_filesize($bytes, $decimals = 2) {
    $sz = 'BKMGT';
    $factor = floor((strlen($bytes) - 1) / 3);
    $unit = @$sz[$factor];
    if( $unit == 'B' ) {
        $decimals = 0;
    }
    return sprintf("%.{$decimals}f ", $bytes / pow(1024, $factor)) . $unit;
}

function mudletsnaps_getSnapshotFileStats($humanFileSize=true) {
    global $mudletsnaps_config;
    
    $dir = $mudletsnaps_config['snapshot_root'] . $mudletsnaps_config['snapshot_files'];
    if( !is_dir($dir) ) {
        return false;
    }
    $files = scandir( $dir );
    $totalCount = 0;
    $totalSize = 0;
    foreach($files as $idx => $file) {
        $filepath = $dir . $file;
        if( $file == '.' || $file == '..' ) {
            continue;
        }
        if( is_dir($filepath) ) {
            continue;
        }
        $totalCount = $totalCount + 1;
        $totalSize = $totalSize + filesize($filepath);
    }
    
    if( $humanFileSize ) {
        return array($totalCount, mudletsnaps_human_filesize($totalSize), $dir);
    } else {
        return array($totalCount, $totalSize, $dir);
    }
}

function mudletsnaps_massDeleteSnapshotFiles() {
    global $mudletsnaps_config;
    
    $file_dir = $mudletsnaps_config['snapshot_root'] . $mudletsnaps_config['snapshot_files'] ;
    $dir_list = scandir($file_dir);
    $fileCount = 0;
    foreach($dir_list as $idx => $file) {
        if( $file == '.' || $file == '..' ) {
            continue;
        }
        $filepath = $file_dir . $file;
        if( is_dir($filepath) ) {
            continue;
        }
        
        if( is_file($filepath) ) {
            $fileCount = $fileCount + 1;
            @unlink($filepath);
        }
    }
    return $fileCount;
}


function mudletsnaps_admin_init() {
    global $pagenow;
    if( $pagenow != 'tools.php' ) {
        return;
    }
    if( !isset($_GET['page']) || $_GET['page'] != 'mudlet-snapshots' ) {
        return;
    }
    
    if( isset($_POST['action']) ) {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        mudletsnaps_tool_page_post();
        
        if( isset($_POST['action2']) ) {
            $tbl = new MudletSnapsUsers_ListTable();
            $tbl->process_bulk_action();
        }
    } elseif( isset($_GET['action']) ) {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
    
        $tbl = new MudletSnapsUsers_ListTable();
        $tbl->process_bulk_action();
    }
}
add_action('admin_init', 'mudletsnaps_admin_init');

function mudletsnaps_admin_menu() {
    add_management_page('Mudlet Snapshots Tools', 'Mudlet Snapshots', 'manage_options', 'mudlet-snapshots', 'mudletsnaps_tool_page');
}
add_action('admin_menu', 'mudletsnaps_admin_menu');

function mudletsnaps_admin_notices() {
    if( isset($_GET['err']) && $_GET['err'] == 'exists' ) { ?>
    <div class="notice notice-error is-dismissible">
        <p>A User by the same Username is already in the Database!</p>
    </div>
    <?php }
    
    if( isset($_GET['nopass']) ) { ?>
    <div class="notice notice-error is-dismissible">
        <p>Edit User failed to update Password!</p>
    </div>
    <?php }
    
    if( isset($_GET['nouser']) ) { ?>
    <div class="notice notice-error is-dismissible">
        <p>Edit User failed to update Username!</p>
    </div>
    <?php }
    
    if( isset($_GET['done']) && $_GET['done'] == 'added' ) { ?>
    <div class="notice notice-success is-dismissible">
        <p>Successfully added a new user!</p>
    </div>
    <?php }
    
    if( isset($_GET['done']) && $_GET['done'] == 'edited' ) { ?>
    <div class="notice notice-success is-dismissible">
        <p>Successfully updated the user!</p>
    </div>
    <?php }
    
    if( isset($_GET['removed']) ) {
      $rmCount = strval(intval($_GET['removed']));
    ?>
    <div class="notice notice-success is-dismissible">
        <p>Successfully removed <?php echo $rmCount; ?> file(s) from the server!</p>
    </div>
    <?php }
    
}
add_action('admin_notices', 'mudletsnaps_admin_notices' );

function mudletsnaps_tool_page() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
    
    if( isset($_GET['action']) && $_GET['action'] == 'edituser') {
        mudletsnaps_tool_page_edit_form();
    } 
    else {
        mudletsnaps_tool_page_base_forms();
    }
}

function mudletsnaps_tool_page_edit_form() {
    $userId = absint($_GET['user']);
    $userData = mudletsnaps_getUserById($userId);
    
    if( $userData == false ) {
        echo('Invalid User!  <a href="tools.php?page=mudlet-snapshots">Go Back</a>');
        exit();
    }
    
    ?>
    <div class="wrap">
    <h1>Mudlet Snapshots - Edit User</h1>
    <form action="tools.php?page=mudlet-snapshots" method="post">
      <label><strong>User ID:</strong> <em><?php echo($userId); ?></em></label><br/>
      <input type="hidden" id="user" name="user" value="<?php esc_attr_e($userId); ?>" />
      <input type="hidden" id="action" name="action" value="edituser" />
      <?php mudletsnaps_nonce_field('mudlet-snapshots'); ?>
      <label><strong>Username:</strong> <input type="text" id="username" name="username" value="<?php esc_attr_e($userData['name']); ?>" /></label><br/>
      <label><strong>Password:</strong> <input type="password" id="password" name="password" value="" /></label><br/>
      <input name="Submit" type="submit" value="Save Changes" />
    </form>
    
    </div>
    <?php
}

function mudletsnaps_tool_page_base_forms() {
    $tbl = new MudletSnapsUsers_ListTable();
    $fstats = mudletsnaps_getSnapshotFileStats();
    ?>
    <style type="text/css">
      input[type=submit].dangerous {
        border: 1px solid rgb(206, 161, 161);
        background: rgb(255, 228, 228);
      }
      input[type=submit].dangerous:hover {
        border-color: #ff8585;
        color: #8e0404;
      }
      .wp-list-table .column-id {
        min-width: 4%;
        max-width: 8%;
        width: 5%;
      }
      @media screen and (max-width: 782px) {
        .wp-list-table .column-id {
          min-width: 100%;
          max-width: 100%;
          width: 100%;
        }
        .wp-list-table tr:not(.inline-edit-row):not(.no-items) td:not(.column-primary)::before {
          content: none;
        }
      }
    </style>
    <script type="text/javascript">
      jQuery(document).ready(function(){
        jQuery("#mdsubmit").click(function(ev){
          var r = confirm("Really Delete ALL Snapshot Files?");
          if( r == false ) {
            ev.preventDefault();
            return false;
          }
        });
      });
    </script>
    <div class="wrap">
        <h1>Mudlet Snapshots Tools</h1>
        <?php if($fstats !== false) { ?>
        <h2>Snapshot Storage Stats</h2>
        <span class="filestats">
          <strong>Files On Disk:</strong> &nbsp; <em><?php echo($fstats[0]); ?></em><br/>
          <strong>Size On Disk:</strong> &nbsp; <em><?php echo($fstats[1]); ?></em><br/>
          <strong>Storage Location:</strong> &nbsp; <em><?php echo($fstats[2]); ?></em><br/>
          <strong>Storage Capacity:</strong> &nbsp; <em><?php mudletsnaps_getMaxCapacityConfig(); ?></em><br/>
        </span>
        <hr/>
        <?php } ?>
        
        <h2>Snapshot User List</h2>
        <form action="tools.php?page=mudlet-snapshots" method="post">
          <?php $tbl->prepare_items();
                $tbl->display(); ?>
        </form>
        <hr/>
        
        <h2>Add New Snapshot User</h2>
        <form action="tools.php?page=mudlet-snapshots" method="post">
          <input type="hidden" name="action" value="adduser"/>
          <?php mudletsnaps_nonce_field('mudlet-snapshots'); ?>
          <label>Username: <input type="text" id="username" name="username" value="" autocomplete="username" /></label>
          <label>Password: <input type="password" id="password" name="password" value="" autocomplete="new-password" /></label>
          <input class="button action" name="Submit" type="submit" value="Add New User" />
        </form>
        <hr/>
        
        <h2>Snapshot Management<h2/>
        <form action="tools.php?page=mudlet-snapshots" method="post">
          <input type="hidden" name="action" value="massdelete_files" />
          <?php mudletsnaps_nonce_field('mudlet-snapshots'); ?>
          <p>Press the button below to immediately delete ALL snapshots from the server.<br/></p>
          <input class="button action dangerous" name="Submit" type="submit" value="Mass Delete Snapshots" id="mdsubmit" />
        </form>
        <hr/>
        
    </div>
    <?php
}

function mudletsnaps_tool_page_post() {
    $nverify = wp_verify_nonce($_POST['_wpnonce'], 'mudlet-snapshots');
    if( $nverify !== false ) {
        $action = strtolower($_POST['action']);
        switch($action) {
            case 'massdelete_files':
                $fileCount = mudletsnaps_massDeleteSnapshotFiles();
                
                wp_redirect( "?page=mudlet-snapshots&removed={$fileCount}" );
                exit();
            break;
            case 'edituser':
                $uid = absint($_POST['user']);
                if( !isset($_POST['user']) ) {
                    wp_die("Invalid Request - Missing User ID!");
                } elseif( mudletsnaps_getUserById($uid) === false ) {
                    wp_die("Invalid Request - User ID not Found!");
                }
                
                $rUser = $rPass = true;
                if( isset($_POST['username']) && !empty($_POST['username']) ) {
                    $rUser = mudletsnaps_editUserName($_POST['user'], $_POST['username']);
                }
                
                if( isset($_POST['password']) && !empty($_POST['password']) ) {
                    $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
                    $rPass = mudletsnaps_editUserPass($_POST['user'], $hash);
                }
                
                $args = "";
                if( !$rPass ) {
                    $args .= "&nopass=1";
                }
                if( !$rUser ) {
                    $args .= "&nouser=1";
                }
                wp_redirect( "?page=mudlet-snapshots&done=edited". $args );
                exit();
            break;
            case 'adduser':
                if( !isset($_POST['username']) || !isset($_POST['password']) ||
                    empty($_POST['username']) || empty($_POST['password']) ) {
                    wp_die("Invalid Request - Missing/Empty Username or Password!");
                }
                
                if( mudletsnaps_getUserByName($_POST['username']) !== false ) {
                    wp_redirect( "?page=mudlet-snapshots&err=exists" );
                    exit();
                }
                
                $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
                mudletsnaps_addUser($_POST['username'], $hash);
                
                wp_redirect( "?page=mudlet-snapshots&done=added" );
                exit();
            break;
        }
    }
}

