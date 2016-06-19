<?php
/*
Plugin Name: VES
Plugin URI: http://greglondon.info
Description: Simple plugin for in-house ticket management that can be customized to fit your organization's current needs.
Author: Greg London
Version: 1.2
Author URI: http://greglondon.info
*/

/*
1/ Release History
- V1 (12-12-2013)
- V1.1 (1-07-2014) - added automatic time, date, and ticket updates
- V1.2 (1-09-2014) - added automatic emails to tier II & III

2/ Plugin description :
This plugin adds a simple ticketing system for use with a small company. This allows current employees to submit tickets to report customer problems. 
This ticketing system was meant to be used as an in house ticketing system only. So only Admins and Contributors can add tickets for security purposes. 
Subscribers and guests are not allowed access. That means this is a completely private and in house ticketing system. 

Contributors have the ability to add, edit, and delete tickets. Users can view other tickets by coworkers, but are not allowed to edit or delete them. 
This plugin creates a custom database table that can be modified as needed.

There is also a search option on the view page to find needed tickets.

3/ Credits :
VES uses the following scripts :
- jQuery
- WordPress

4/ License terms :
- None

*/


/*************************************/
/*                                   */
/* Definitions and globals */
/*                                   */
/*************************************/

define('VES_URL',get_option('siteurl').'/wp-content/plugins/'.basename(dirname(__FILE__)).'/');
define('VES_PATH',ABSPATH.'wp-content/plugins/'.basename(dirname(__FILE__)).'/');
define('VES_DEBUG', false);

global $id;
$id = ves_theid($id);

$ves_settings = array();
$ves_version = '1.2';
$ves_current_post_author = 0;


/******************************************/
/*                                        */
/* Function to Initialize */
/*                                        */
/******************************************/


function ves_debug($msg)
{
	if (VES_DEBUG)
	{
	    $today = date("d/m/Y H:i:s");
	    $myFile = dirname(__file__) . "/debug.log";
	    $fh = fopen($myFile, 'a') or die("Can't open debug file. Please manually create the 'debug.log' file (inside the 'wats' directory) and make it writable.");
	    $ua_simple = preg_replace("/(.*)\s\(.*/","\\1",$_SERVER['HTTP_USER_AGENT']);
	    fwrite($fh, $today . " [from: ".$_SERVER['REMOTE_ADDR']."|$ua_simple] - " . $msg . "\n");
	    fclose($fh);
	}
}

/************************************/
/*                                  */
/* Function to create database */
/*                                  */
/************************************/

function ves_install () {
   global $wpdb;

   $table_name = $wpdb->prefix . "ves"; 
   
   if ($wpdb->get_var("show tables like '$table_name'") != $table_name) {
   
   $sql = "CREATE TABLE `{$table_name}` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ticket` tinytext NOT NULL,
  `date`  tinytext NOT NULL,
  `time`  tinytext NOT NULL,
  `agent` tinytext NOT NULL,
  `phone` tinytext NOT NULL,
  `fname` tinytext NOT NULL,
  `lname` tinytext NOT NULL,
  `state` tinytext NOT NULL,
  `email` tinytext NOT NULL,
  `issue` tinytext NOT NULL,
  `tcall` tinytext NOT NULL,
  `resolution` text NOT NULL,
  `rdate` tinytext NOT NULL,
  `status` tinytext NOT NULL,
  `trep` tinytext NOT NULL,
  `user` tinytext NOT NULL
);";

require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
dbDelta( $sql );
	}
}

/************************************/
/*                                  */
/* Functions for plugin */
/*                                  */
/************************************/

function ves_plugin_menu() {

	add_options_page( 'VES Plugin Options', 'VES', 'edit_posts', 'ves-unique-identifier', 'ves_plugin_options' );
}

function ves_plugin_options() {
	
	if ( !current_user_can( 'edit_posts' ) ) 
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );

	echo '<div class="wrap">';
	echo '<h1>Enter a New Ticket into the database by using the VES tool on the side menu</h1>';
	echo '<p>That is all for now, bye!!!</p>';
	echo '</div>';
}

function ves_create_menu() {

	$page_title = 'VES Plugin Tickets';
	$menu_title = 'VES Tickets';
	$capability = 'edit_posts';
	$menu_slug = 'ves-add-tickets';
	$function = 'ves_add_tickets_page';
	$sub_menu_title = 'Add Tickets';
	
	//create new top-level menu
	add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, plugins_url('/img/support.png', __FILE__));
	
	// Add submenu page with same slug as parent to ensure no duplicates
    add_submenu_page($menu_slug, $page_title, $sub_menu_title, $capability, $menu_slug, $function);	
	
	$submenu_page_title2 = 'VES Edit Tickets';
    $submenu_title2 = 'Edit Tickets';
    $submenu_slug2 = 'ves-edit-tickets';
    $submenu_function2 = 'ves_edittickets';
    add_submenu_page($menu_slug, $submenu_page_title2, $submenu_title2, $capability, $submenu_slug2, $submenu_function2);
	
	$submenu_page_title = 'VES View All Tickets';
    $submenu_title = 'View All Tickets';
    $submenu_slug = 'ves-view-tickets';
    $submenu_function = 'ves_viewtickets';
    add_submenu_page($menu_slug, $submenu_page_title, $submenu_title, $capability, $submenu_slug, $submenu_function);
	
	$submenu_page_title3 = 'VES View Your Tickets';
    $submenu_title3 = 'View Your Tickets';
    $submenu_slug3 = 'ves-view-yourtickets';
    $submenu_function3 = 'ves_viewyourtickets';
    add_submenu_page($menu_slug, $submenu_page_title3, $submenu_title3, $capability, $submenu_slug3, $submenu_function3);
	
}

function ves_viewyourtickets() {

	global $wpdb, $user_ID;
	
	echo '<div class="wrap"><div id="icon-tools" class="icon32"></div>';
	echo '<h2>Your Current Tickets</h2><fieldset>';
		
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM `wp_ves` WHERE `user` = '{$user_ID}'" );
	echo '<h2 align="center">You have a total of <b>' . $count. '</b> ticket(s) in the database.</h2>';
	
	// begin pagination	 
	$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
	$limit = 25; // number of rows in page
	$offset = ( $pagenum - 1 ) * $limit;
	$total = $wpdb->get_var( "SELECT COUNT(`id`) FROM `wp_ves` WHERE `user` = '{$user_ID}'" );
	$num_of_pages = ceil( $total / $limit );
	$result = $wpdb->get_results( "SELECT `id`,`ticket`,`fname`,`lname`, `agent`, `phone`, `date`, `time`, `trep`, `status` FROM `wp_ves` WHERE `user` = '{$user_ID}' LIMIT $offset, $limit" );

	$data_html = '';
	echo '<table border="1" width="100%"><tr><th>ID</th><th>Ticket</th><th>First Name</th><th>Last Name</th><th>Agent</th><th>Phone</th><th>Date</th><th>Time</th><th>Tech Rep</th><th>Status</th></tr>';
	foreach( $result as $results ) {

	    $id=$results->id;
	    $ticket= $results->ticket;
	    $fname= $results->fname;
	    $lname= $results->lname;
		$agent=$results->agent;
		$phone=$results->phone;
		$date=$results->date;
		$time=$results->time;
		$trep=$results->trep;
		$status=$results->status;

		?>

		<?php $html= "<tr>";?>
		<?php $html .= "<td style=\"text-align:center;\"><a title=\"Click here to view or edit this ticket\" href=\"?page=ves-edit-tickets&tk=" . $id . "\">". $id."</a></td>";?>
		<?php $html .= "<td>". $ticket."</td>";?>
		<?php $html .= "<td>".$fname."</td>";?>
		<?php $html .= "<td>".  $lname ."</td>";?>
	    <?php $html .= "<td>".  $agent ."</td>";?>
	    <?php $html .= "<td>".  $phone ."</td>";?>
	    <?php $html .= "<td>".  $date ."</td>";?>
	    <?php $html .= "<td>".  $time ."</td>";?>
	    <?php $html .= "<td>".  $trep ."</td>";?>
	    <?php $html .= "<td>".  $status ."</td>";?>
		<?php $html .= "</tr>"?>
		<?php
		$data_html .=$html; 
	}

	echo $data_html;

	$page_links = paginate_links( array(
	    'base' => add_query_arg( 'pagenum', '%#%' ),
	    'format' => '',
	    'prev_text' => __( '&laquo;', 'aag' ),
	    'next_text' => __( '&raquo;', 'aag' ),
	    'total' => $total,
	    'current' => $pagenum
	) );

	if ( $page_links ) {
	    echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . 
		$page_links . '</div></div>';
	}

	// end pagination
	
	ves_search();
	
	echo '</fieldset></div>';
	
}

function ves_get_ticket_prefix() {

	global $user_email;

	//TODO make this dynamic where admin ads contributors...
	
	if ($user_email == 'G@v.com')
		$pre = 'GL';
	elseif ($user_email == 'H@v.com')
		$pre = 'HA';
	else 
		$pre = 'NA';
	
	return $pre;

}

function ves_get_ticket_count() {

	global $wpdb, $user_ID;
	
	$count = $wpdb->get_var( "SELECT COUNT(`id`) FROM `wp_ves` WHERE `user` = '{$user_ID}'" );
	return $count;
}

function ves_add_tickets_page() {

	global $wpdb, $user_ID, $user_email;

	$table = 'wp_ves';
	
	if (!empty($_POST)) {
	
		$new = ves_get_ticket_count() + 1;
		
		date_default_timezone_set('America/Chicago');

		$ticket = ves_get_ticket_prefix() . '0000' . $new;
		$date = date("m/d/Y");
		$time = date("H:i");
		$agent = esc_html($_POST['agent']);
		$phone = esc_html($_POST['phone']);
		$fname = esc_html($_POST['fname']);
		$lname = esc_html($_POST['lname']);
		$state = esc_html($_POST['state']);
		$email = esc_html($_POST['email']);
		$issue = esc_html($_POST['issue']);
		$tcall = esc_html($_POST['tcall']);
		$resolution = esc_html($_POST['resolution']);
		$rdate = esc_html($_POST['rdate']);
		$status = esc_html($_POST['status']);
		$trep = esc_html($_POST['trep']);
		$user = $user_ID;

		$wpdb->insert(
		  $table,
		  array( 'ticket' => $ticket, 
		  		 'date' => $date,
				 'time' => $time,
				 'agent' => $agent,
				 'phone' => $phone,
				 'fname' => $fname,
				 'lname' => $lname,
				 'state' => $state,
				 'email' => $email,
				 'issue' => $issue,
				 'tcall' => $tcall,
				 'resolution' => $resolution,
				 'rdate' => $rdate,
				 'status' => $status,
				 'trep' => $trep,
				 'user' => $user
			    )
			);
	
			if (isset($_POST['tier2'])) {
			
				$to = 't@.com';
				//$headers = 'From: '.$trep.' <'.$user_email.'>' . . "\r\n"; 
				$subject = 'Escalated Tier II Ticket -  '. date("m/d/Y").' - '. $time .'.';
				$message = '<p>Ticket Info is as follows:</p>
					<ul>
					<li><b>Issue:</b> '.$issue.'</li>
					<li><b>Ticket:</b> '.$ticket.'</li>
					<li><b>Date:</b> '.$date.'</li>
					<li><b>Time:</b> '.$time.'</li>
					<li><b>Agent:</b> '.$agent.'</li>
					<li><b>Phone:</b> '.$phone.'</li>
					<li><b>First:</b> '.$fname.'</li>
					<li><b>Last:</b> '.$lname.'</li>
					<li><b>ST:</b> '.$state.'</li>
					<li><b>Email:</b> '.$email.'</li>
					<li><b>Length:</b> '.$tcall.'</li>
					<li><b>Status:</b> '.$status.'</li>
					<li><b>Tech Rep:</b> '.$trep.'</li>
					</ul>
				';
		
				wp_mail( $to, $subject, $message );
				
				if (wp_mail( $to, $subject, $message ) == 1) 
					$message = 'An escalation email was successfully sent to Tier II.';
				else 
					$message = 'You should probably ask Tier II if they got the email because something went wrong...';
	
			}
	
			if (isset($_POST['tier3'])) {
				
				$to2 = 'g@g.com, t@h.com';
				$headers2[] = 'From: '.$trep.' <'.$user_email.'>';
				$headers2[] = 'Cc: LH <l@v.com>';
				$subject2 = 'Escalated Tier III Ticket -  '. date("m/d/Y").' - '. $time .'.';
				$message2 = '<p>Ticket Info is as follows:</p>
					<ul>
					<li><b>Issue:</b> '.$issue.'</li>
					<li><b>Ticket:</b> '.$ticket.'</li>
					<li><b>Date:</b> '.$date.'</li>
					<li><b>Time:</b> '.$time.'</li>
					<li><b>Agent:</b> '.$agent.'</li>
					<li><b>Phone:</b> '.$phone.'</li>
					<li><b>First:</b> '.$fname.'</li>
					<li><b>Last:</b> '.$lname.'</li>
					<li><b>ST:</b> '.$state.'</li>
					<li><b>Email:</b> '.$email.'</li>
					<li><b>Length:</b> '.$tcall.'</li>
					<li><b>Status:</b> '.$status.'</li>
					<li><b>Tech Rep:</b> '.$trep.'</li>
					</ul>
				';
		
				wp_mail( $to2, $subject2, $message2, $headers2 );
				
				if (wp_mail( $to2, $subject2, $message2, $headers2 ) == 1) 
					$message2 = 'An escalation email was successfully sent to Tier III.';
				else
					$message2 = 'You should probably ask Tier III if they got the email because something went wrong...';

			}
	
		ves_submitted($message, $message2);
		
  } else {
	
		?>

		<div class="wrap">
		<h2>Add a New Ticket</h2>

		<form method="post" action="?page=ves-add-tickets">
		   <fieldset> 
		    <table class="form-table">
		       
		        <tr valign="top">
		        <th scope="row">Agent</th>
		        <td><input type="text" name="agent" value="<?php if (isset($_POST['agent'])) echo $_POST['agent']; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Phone#</th>
		        <td><input type="text" name="phone" value="<?php if (isset($_POST['phone'])) echo $_POST['phone']; ?>" /></td>
		        </tr>
        
		        <tr valign="top">
		        <th scope="row">First Name</th>
		        <td><input type="text" name="fname" value="<?php if (isset($_POST['fname'])) echo $_POST['fname']; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Last Name</th>
		        <td><input type="text" name="lname" value="<?php if (isset($_POST['lname'])) echo $_POST['lname']; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">State</th>
		        <td><input type="text" name="state" value="<?php if (isset($_POST['state'])) echo $_POST['state']; ?>" /></td>
		        </tr>
        
		        <tr valign="top">
		        <th scope="row">Email Address</th>
		        <td><input type="text" name="email" size="50" value="<?php if (isset($_POST['email'])) echo $_POST['email']; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Issue</th>
		        <td><input type="text" name="issue" value="<?php if (isset($_POST['issue'])) echo $_POST['issue']; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Resolution</th>
		        <td><textarea name="resolution" rows="10" cols="20"><?php if (isset($_POST['resolution'])) echo $_POST['resolution']; ?></textarea></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Time on Call</th>
		        <td><input type="text" name="tcall" value="<?php if (isset($_POST['tcall'])) echo $_POST['tcall']; ?>" /></td>
		        </tr>
        
		        <tr valign="top">
		        <th scope="row">Resolution Date</th>
		        <td><input type="text" name="rdate" value="<?php if (isset($_POST['rdate'])) echo $_POST['rdate']; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Status</th>
		        <td><input type="text" name="status" value="<?php if (isset($_POST['status'])) echo $_POST['status']; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Tech Rep</th>
		        <td><input type="text" name="trep" value="<?php if (isset($_POST['trep'])) echo $_POST['trep']; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Escalate - Tier II</th>
		        <td><input type="checkbox" name="tier2" value="1" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Escalate - Tier III</th>
		        <td><input type="checkbox" name="tier3" value="1" /> This notifies Tier II as well.</td>
		        </tr>
        
    		</table>
    
    		<?php submit_button('Add Ticket', 'add', 'add'); ?>

		</fieldset>
		</form>
		</div>

<?php 

	}
} 

function ves_submitted($message, $message2) {

	echo '<div class="wrap">';
	echo '<h2>Your ticket has been added to the database</h2>';
	echo '<ul class="indent">
		  <li><a href="?page=ves-view-tickets">View Tickets</a></li>
		  </ul>';

	if ($message != '' || $message2 != '') {
	  	echo '<p>'.$message.'</p>';
	  	echo '<p>'.$message2.'</p>';
	}
	echo '</div>';
}

function ves_edit_submitted() {

	echo '<div class="wrap">';
	echo '<h2>Your ticket has been updated</h2>';
	echo '<ul class="indent">
			  <li><a href="?page=ves-view-tickets">View Tickets</a></li>
			  </ul>';
	echo '</div>';
}

function ves_checkuser() {

	global $user_ID;
	
	return $user_ID;
}

function ves_edittickets() {

	global $wpdb, $wp_query, $user_ID;
	global $id;
	$currentuser = ves_checkuser();
	
	if (isset($wp_query->query_vars['tk'])) 
		print $wp_query->query_vars['tk'];

	if ( get_magic_quotes_gpc() ) {
    
	    $_GET       = array_map( 'stripslashes_deep', $_GET );
	    $_REQUEST   = array_map( 'stripslashes_deep', $_REQUEST );
	}

	$id = stripslashes_deep($_GET['tk']);
	ves_theid($id);
	$table = 'wp_ves';
		
	echo '<div class="wrap"><div id="icon-tools" class="icon32"></div>';
	echo '<h2>Edit Ticket</h2>';
		
	$result = $wpdb->get_row("SELECT * FROM $table WHERE id = $id");
	
	if ($id == '') {

		echo '<p>Please search or select a ticket from the list below before editing.</p>';
		ves_viewtickets();

	} elseif ($result->user != $currentuser) {

		echo '<fieldset><h2>You didn\'t create this ticket so you can only view it and not edit it.</h2><br />';
		echo '<table border="1" width="25%">
				<tr><td>ID</td><td>'.$result->id.'</td></tr>
				<tr><td>Ticket</td><td>'.$result->ticket.'</td></tr>
				<tr><td>Date</td><td>'.$result->date.'</td></tr>
				<tr><td>Time</td><td>'.$result->time.'</td></tr>
				<tr><td>Agent</td><td>'.$result->agent.'</td></tr>
				<tr><td>Phone</td><td>'.$result->phone.'</td></tr>
				<tr><td>First Name</td><td>'.$result->fname.'</td></tr>
				<tr><td>Last Name</td><td>'.$result->lname.'</td></tr>
				<tr><td>State</td><td>'.$result->state.'</td></tr>
				<tr><td>Email</td><td>'.$result->email.'</td></tr>
				<tr><td>Issue</td><td>'.$result->issue.'</td></tr>
				<tr><td>Time on Call</td><td>'.$result->tcall.'</td></tr>
				<tr><td>Resolution</td><td>'.$result->resolution.'</td></tr>
				<tr><td>Resolution Date</td><td>'.$result->rdate.'</td></tr>
				<tr><td>Status</td><td>'.$result->status.'</td></tr>
				<tr><td>Tech Rep</td><td>'.$result->trep.'</td></tr>
			  </table>';
		echo '</fieldset>';
	
	} elseif (!empty($_POST['update'])) {

		$ticket = esc_html($_POST['ticket']);
		$date = esc_html($_POST['date']);
		$time = esc_html($_POST['time']);
		$agent = esc_html($_POST['agent']);
		$phone = esc_html($_POST['phone']);
		$fname = esc_html($_POST['fname']);
		$lname = esc_html($_POST['lname']);
		$state = esc_html($_POST['state']);
		$email = esc_html($_POST['email']);
		$issue = esc_html($_POST['issue']);
		$tcall = esc_html($_POST['tcall']);
		$resolution = esc_html($_POST['resolution']);
		$rdate = esc_html($_POST['rdate']);
		$status = esc_html($_POST['status']);
		$trep = esc_html($_POST['trep']);
	
		$updatesql = "UPDATE `{$table}` SET `ticket` = '{$ticket}', 
		`date` = '{$date}', 
		`time` = '{$time}',
		`agent` = '{$agent}',
		`phone` = '{$phone}',
		`fname` = '{$fname}',
		`lname` = '{$lname}',
		`state` = '{$state}',
		`email` = '{$email}',
		`issue` = '{$issue}',
		`tcall` = '{$tcall}',
		`resolution` = '{$resolution}',
		`rdate` = '{$rdate}',
		`status` = '{$status}',
		`trep` = '{$trep}'
		WHERE `id` = '{$id}';"; 
	
	 
		$wpdb->query($updatesql);
		
		ves_edit_submitted();
		
    } elseif (!empty($_POST['delete'])) {
  
		$delete = "DELETE FROM `{$table}` WHERE `id` = '{$id}';";  
		$wpdb->query($delete);
		
		ves_ticketd();
  
  	} else {
		
		?>
        
		<form method="post" action="?page=ves-edit-tickets&tk=<?php if ($result->id != '') echo $result->id;?>">
		   <fieldset> 
		    <table class="form-table">
		        <tr valign="top">
		        <th scope="row">Ticket #</th>
		        <td><input type="text" name="ticket" value="<?php if ($result->ticket != '') echo $result->ticket; ?>" /></td>
		        </tr>
		         
		        <tr valign="top">
		        <th scope="row">Date</th>
		        <td><input type="text" name="date" value="<?php if ($result->date != '') echo $result->date; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Time</th>
		        <td><input type="text" name="time" value="<?php if ($result->time != '') echo $result->time; ?>" /></td>
		        </tr>
        
		        <tr valign="top">
		        <th scope="row">Agent</th>
		        <td><input type="text" name="agent" value="<?php if ($result->agent != '') echo $result->agent; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Phone#</th>
		        <td><input type="text" name="phone" value="<?php if ($result->phone != '') echo $result->phone; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">First Name</th>
		        <td><input type="text" name="fname" value="<?php if ($result->fname != '') echo $result->fname; ?>" /></td>
		        </tr>
        
		        <tr valign="top">
		        <th scope="row">Last Name</th>
		        <td><input type="text" name="lname" value="<?php if ($result->lname != '') echo $result->lname; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">State</th>
		        <td><input type="text" name="state" value="<?php if ($result->state != '') echo $result->state; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Email Address</th>
		        <td><input type="text" name="email" size="50" value="<?php if ($result->email != '') echo $result->email; ?>" /></td>
		        </tr>
        
		        <tr valign="top">
		        <th scope="row">Issue</th>
		        <td><input type="text" name="issue" value="<?php if ($result->issue != '') echo $result->issue; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Resolution</th>
		        <td><textarea name="resolution" rows="10" cols="50"><?php if ($result->resolution != '') echo $result->resolution; ?></textarea></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Time on Call</th>
		        <td><input type="text" name="tcall" value="<?php if ($result->tcall != '') echo $result->tcall; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Resolution Date</th>
		        <td><input type="text" name="rdate" value="<?php if ($result->rdate != '') echo $result->rdate; ?>" /></td>
		        </tr>
        
		        <tr valign="top">
		        <th scope="row">Status</th>
		        <td><input type="text" name="status" value="<?php if ($result->status != '') echo $result->status; ?>" /></td>
		        </tr>
		        
		        <tr valign="top">
		        <th scope="row">Tech Rep</th>
		        <td><input type="text" name="trep" value="<?php if ($result->trep != '') echo $result->trep; ?>" /></td>
		        </tr>
		        
		    </table>
    
    		<?php submit_button('Update', 'update', 'update'); ?>
    
    		<div class="delete">

    			<?php ves_delete(); ?>
			</div>
    
    	</fieldset>
	</form>
</div>

<?php

	}
}

function ves_theid($id) {
	return $id;
}

function ves_delete() {
	
	echo '<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>
	<script>
	$(document).ready(function() {									
	$("a[name^=\'faq-\']").each(function() {
		$(this).click(function() {
			if( $("#" + this.name).is(\':hidden\') ) {
				$("#" + this.name).toggle(\'slow\');
			} else {
				$("#" + this.name).toggle(\'slow\');
			}			
			return false;
		});
	});
});
</script>';
	
	echo '<div class="backing"><ul><li><h2><a href="#" name="faq-1" title="Click here to open and close." class="delete">Delete</a></h2>.<div class="faq-answer" id="faq-1"><br />';
	echo '<h2>Are you sure you want to delete this ticket?</h2>';
    echo '<form method="post" action="?page=ves-edittickets">';
    	submit_button('Delete', 'delete', 'delete'); 
    echo '</form><br /><br /></div></li></ul></div>';
}

function ves_ticketd() {

	echo '<div class="wrap">';
	echo '<h2>The ticket has been deleted</h2>';
	echo '<ul class="indent">
			  <li><a href="?page=ves-view-tickets">View Tickets</a></li>
			  </ul>';
	echo '</div>';
}

function ves_viewtickets() {

	global $wpdb;
	
	echo '<div class="wrap"><div id="icon-tools" class="icon32"></div>';
	echo '<h2>Current Tickets</h2><fieldset>';
		
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM wp_ves" );
	echo '<h2 align="center">There is a total of <b>' . $count. '</b> ticket(s) in the database.</h2>';
	
	// begin pagination	 
	$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;

	$limit = 25; // number of rows in page
	$offset = ( $pagenum - 1 ) * $limit;
	$total = $wpdb->get_var( "SELECT COUNT(`id`) FROM `wp_ves`" );
	$num_of_pages = ceil( $total / $limit );
	$result = $wpdb->get_results( "SELECT `id`,`ticket`,`fname`,`lname`, `agent`, `phone`, `date`, `time`, `trep`, `status` FROM `wp_ves` LIMIT $offset, $limit" );
	$data_html = '';

	echo '<table border="1" width="100%"><tr><th>ID</th><th>Ticket</th><th>First Name</th><th>Last Name</th><th>Agent</th><th>Phone</th><th>Date</th><th>Time</th><th>Tech Rep</th><th>Status</th></tr>';
	
	foreach( $result as $results ) {

	    $id=$results->id;
	    $ticket= $results->ticket;
	    $fname= $results->fname;
	    $lname= $results->lname;
		$agent=$results->agent;
		$phone=$results->phone;
		$date=$results->date;
		$time=$results->time;
		$trep=$results->trep;
		$status=$results->status;

		?>

		<?php $html= "<tr>";?>
		<?php $html .= "<td style=\"text-align:center;\"><a title=\"Click here to view or edit this ticket\" href=\"?page=ves-edit-tickets&tk=" . $id . "\">". $id."</a></td>";?>
		<?php $html .= "<td>". $ticket."</td>";?>
		<?php $html .= "<td>".$fname."</td>";?>
		<?php $html .= "<td>".  $lname ."</td>";?>
	    <?php $html .= "<td>".  $agent ."</td>";?>
	    <?php $html .= "<td>".  $phone ."</td>";?>
	    <?php $html .= "<td>".  $date ."</td>";?>
	    <?php $html .= "<td>".  $time ."</td>";?>
	    <?php $html .= "<td>".  $trep ."</td>";?>
	    <?php $html .= "<td>".  $status ."</td>";?>
		<?php $html .= "</tr>"?>
		<?php
		$data_html .=$html; 
	}

	echo $data_html;

	$page_links = paginate_links( array(
	    'base' => add_query_arg( 'pagenum', '%#%' ),
	    'format' => '',
	    'prev_text' => __( '&laquo;', 'aag' ),
	    'next_text' => __( '&raquo;', 'aag' ),
	    'total' => $total,
	    'current' => $pagenum
	) );

	if ( $page_links ) {
	    echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . 
		$page_links . '</div></div>';
	}

	// end pagination
	
	ves_search();
	
	echo '</fieldset></div>';

}

function ves_search() {

	global $wpdb;
	
	echo '<br /><h2>Search for a ticket:</h2>';
    echo '<form method="post" action="?page=ves-view-tickets">';

	?>
    Search in:
    <select name="where">
		<option value="ticket">Tickets</option>
		<option value="fname">First Name</option>
		<option value="lname">Last Name</option>
		<option value="agent">Agent</option>
    <option value="email">Email</option>
    <option value="state">State</option>
    <option value="date">Date</option>
    <option value="time">Time</option>
    <option value="phone">Phone</option>
    <option value="issue">Issue</option>
    <option value="resolution">Resolution</option>
    <option value="rdate">Resolution Date</option>
    <option value="status">Status</option>
	</select> 
	<input type="text" name="s" value="<?php if (isset($_POST['s'])) echo $_POST['s']; ?>" />
    <?php
	submit_button('Search', 'search', 'search'); 
    echo '</form>';
		
	if (!empty($_POST['search'])) {

		$s = esc_html($_POST['s']);
		$where = esc_html($_POST['where']);
		
		$sql = "SELECT * FROM `wp_ves` WHERE `{$where}` LIKE '%{$s}%' LIMIT 10;";
		$result = $wpdb->get_results($sql);
		
		if (empty($result))
			echo 'No results were found, try to select another table to search in.';
	
		$num = count($result);
		echo '<h2>'.$num.' Result(s) Found for "'.$s.'" (10 is the max)</h2><ul>';
		
		foreach ($result as $results) {

			$id = $results->id;
			$ticket = $results->ticket;
			$fname = $results->fname;
			$lname = $results->lname;

			echo '<li><a href="?page=ves-edit-tickets&tk='.$id.'">'.$id.'  -  '.$ticket.'  -  '.$fname.'  -  '.$lname.'</a></li>';
		
		}
		
		echo '</ul>';
	}
}

/***************************************************/
/*                                                 */
/* Function to activate plugin */
/*                                                 */
/***************************************************/

function ves_activation()
{
	global $ves_version;
	
	ves_debug("VES ".$ves_version." activated.");
	
	return;
}


/*******************************************************/
/*                                                     */
/* Function to deactivate plugin */
/*                                                     */
/*******************************************************/

function ves_deactivation()
{
	global $wpdb, $ves_version;

	ves_debug("VES ".$ves_version." deactivated.");
	
	// simply drop the table so we can start from scratch....
	$table = wp_ves;
	
	$delete = "DROP TABLE `{$table}`;";  
	$wpdb->query($delete);
	
	return;
}

/*******************************************************/
/*                                                     */
/* Functions - other, actions and hooks */
/*                                                     */
/*******************************************************/

function ves_add_my_stylesheet() { 

    $plugin_url = trailingslashit(get_option('siteurl')) . 'wp-content/plugins/' . basename(dirname(__FILE__)) .'/';
	$myStyleFile = $plugin_url."css/ves.css";
	wp_register_style('ves_css', $myStyleFile); 
	wp_enqueue_style('ves_css');
	
	return;
}

function ves_set_content_type($content_type){
	return 'text/html';
}

function parameter_queryvars( $qvars ) {
	$qvars[] = 'tk';
	return $qvars;
}

add_filter('query_vars', 'parameter_queryvars' );
add_filter('wp_mail_content_type','ves_set_content_type');
add_filter('wp_mail_from','ves_wp_mail_from');

function ves_wp_mail_from($content_type) {

    return 'noreply@v.com';
}

add_filter('wp_mail_from_name','ves_wp_mail_from_name');

function ves_wp_mail_from_name($name) {

    return 'Escalated Ticket';
}

register_activation_hook( __FILE__, 'ves_install' );
register_activation_hook(__FILE__, 'ves_activation');
register_deactivation_hook(__FILE__, 'ves_deactivation');

add_action( 'admin_menu', 'ves_plugin_menu' );
add_action( 'admin_menu', 'ves_create_menu' );
add_action( 'admin_print_styles', 'ves_add_my_stylesheet' );


// note this is only for v server mail use.... (needs to be updated)
add_action( 'phpmailer_init', 'wpse8170_phpmailer_init' );

function wpse8170_phpmailer_init( PHPMailer $phpmailer ) {

    $phpmailer->Host = 'smtp.gmail.com';
    $phpmailer->Port = 587; // could be different
    $phpmailer->Username = 's@g.com'; // if required
    $phpmailer->Password = 'NotAvailable'; // if required
    $phpmailer->SMTPAuth = true; // if required
    $phpmailer->SMTPSecure = 'tls'; // enable if required, 'ssl' is another possible value
    $phpmailer->IsSMTP();
}

?>
