<?php

use Twilio\Rest\Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Class Africa_DMM_Workflows
 *
 * @since  1.11.0
 */
class Africa_DMM_Workflows {

    /**
     * Africa_DMM_Workflows The single instance of Africa_DMM_Workflows.
     *
     * @var    object
     * @access private
     * @since  1.11.0
     */
    private static $_instance = null;

    /**
     * Main Africa_DMM_Workflows Instance
     *
     * Ensures only one instance of Africa_DMM_Workflows is loaded or can be loaded.
     *
     * @return Africa_DMM_Workflows instance
     * @since  1.11.0
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Africa_DMM_Workflows constructor.
     */
    public function __construct() {
        add_action( 'dt_twilio_message_received', [ $this, 'dt_twilio_message_received' ], 10, 3 );
    }


    public function send_whatsapp_message( $from, $to, $message ){
        $token = get_option( Disciple_Tools_Twilio_API::$option_twilio_token );
        $sid = get_option( Disciple_Tools_Twilio_API::$option_twilio_sid );

        $twilio = new Client( $sid, $token );

        $message = $twilio->messages
            ->create( $to,
                [
                    'from' => $from,
                    'body' => $message,
                ]
            );
            dt_write_log( $message->sid );
    }

    public function dt_twilio_message_received( $type, $params, $conversation_post_id = null ){

        if ( $type !== 'whatsapp' || empty( $conversation_post_id ) ){
            return;
        }
        $comment = $params['Body'];

        $pieces = explode( ',', $comment );
        $conversation = DT_Posts::get_post( 'conversations', $conversation_post_id, true, false );
        $link = '[' . $params['From'] . '](' . $conversation['permalink'] . ')';

        //trim each piece
        $pieces = array_map( 'trim', $pieces );

        if ( is_numeric( $pieces[0] ) && count( $pieces ) === 9 ){
            $group = DT_Posts::get_post( 'groups', $pieces[0], true, false );
            if ( is_wp_error( $group ) ){
                $this->send_whatsapp_message( $params['To'], $params['From'], 'Group not found' );
                return;
            }
            $this->process_group_update( $pieces, $link, $params );
        } else if ( strtolower( $pieces[0] ) === 'new' && count( $pieces ) === 5 ){
            $this->process_new_group( $pieces, $link, $params );
        } else if ( is_numeric( $pieces[0] ) && count( $pieces ) === 2 ){
            $this->process_text_update( $pieces, $link, $params );
        } else if ( strtolower( $pieces[0] ) === 'help' ){
            $this->help_format( $params );
        } else {
            $this->send_whatsapp_message( $params['To'], $params['From'], 'Sorry, please try again. Send "help" for the expected message format' );
        }
        return;
    }

    public function help_format( $params ){
        $message = 'To update a group send: 
- group #
- name of group leader
- name of coach
- # unbelievers
- # baptized believers
- # in group in an accountability group
- Y/N church
- # in group who have started a new group
- # of groups this group has started';
        $message .= "\n";
        $message .= "\n";
        $message .= 'Your update should look like:';
        $message .= "\n";
        $message .= "\n";
        $message .= '135, Simon, Jonah, 4, 6, 3, Y, 3, 6';
        $message .= "\n";
        $message .= "\n";
        $message .= 'To create a new group send:
- new
- parent group #
- group name
- group location
- start date';
        $message .= "\n";
        $message .= "\n";
        $message .= 'Your message should look like:';
        $message .= "\n";
        $message .= "\n";
        $message .= 'new, 135, Simon\'s house church, Buipe, 2023-04-04';

        $message .= "\n";
        $message .= "\n";
        $message .= 'To send a text update to a group send:
- group #
- text update';
        $message .= "\n";
        $message .= "\n";
        $message .= 'Your message should look like:';
        $message .= "\n";
        $message .= "\n";
        $message .= '135, We had a great time of prayer and fasting today.';

        $this->send_whatsapp_message( $params['To'], $params['From'], $message );
    }

    public function process_group_update( $pieces, $link, $params ){
        /**
         * 0. group #
         * 1. name group leader
         * 2. name of coach
         * 3. # unbelievers
         * 4. # baptized believers
         * 5. # in group in an accountability group
         * 6. Y/N church
         * 7. # in group who have started new group
         * 8. # of groups this group has started
         *
         * example: 135, Simon, Jonah, 4, 6, 3, Y, 3, 6
         */

        $group_id = (int) $pieces[0];
        $name_group_leader = $pieces[1];
        $name_of_coach = $pieces[2];
        $unbelievers = (int) $pieces[3];
        $baptized_believers = (int) $pieces[4];
        $accountability_group = (int) $pieces[5];
        $church = $pieces[6];
        $started_new_group = $pieces[7];
        $new_groups = $pieces[8];

        $group_update_comment = 'Update Received from: ' . $link;
        $group_update_comment .= "\n\n";
        $group_update_comment .= 'Group Leader: ' . $name_group_leader;
        $group_update_comment .= "\n";
        $group_update_comment .= 'Coach: ' . $name_of_coach;
        $group_update_comment .= "\n";
        $group_update_comment .= 'Unbelievers #: ' . $unbelievers;
        $group_update_comment .= "\n";
        $group_update_comment .= 'Baptized Believers #: ' . $baptized_believers;
        $group_update_comment .= "\n";
        $group_update_comment .= 'Accountability Group #: ' . $accountability_group;
        $group_update_comment .= "\n";
        $group_update_comment .= 'Is Church: ' . $church;
        $group_update_comment .= "\n";
        $group_update_comment .= '# who Started New Group: ' . $started_new_group;
        $group_update_comment .= "\n";
        $group_update_comment .= 'New Groups #: ' . $new_groups;


        $group_update = [
            'notes' => [ $group_update_comment ],
            'member_count' => $unbelievers + $baptized_believers,
            'four_fields_unbelievers' => $unbelievers,
            'four_fields_believers' => $baptized_believers,
            'four_fields_accountable' => $accountability_group,
            'four_fields_church_commitment' => strtolower( $church ) === 'y' ? 'Y' : 'N',
            'four_fields_multiplying' => ( $started_new_group ?? 0 ) . ' - ' . ( $new_groups ?? 0 ),
            'dmm_loader' => $name_group_leader,
            'dmm_coach' => $name_of_coach,
        ];

        $tets = DT_Posts::update_post( 'groups', $group_id, $group_update, false, false );
        $this->send_whatsapp_message( $params['To'], $params['From'], 'Thank You' );

    }


    public function process_new_group( $pieces, $link, $params ){
        /**
         * 0. new
         * 1. parent group
         * 2. group name
         * 3. group location
         * 4. start date
         */

        $parent_group = (int) $pieces[1];
        $group_name = $pieces[2];
        $group_location = $pieces[3];
        $start_date = $pieces[4];

        $group_update_comment = 'New Group Created by: ' . $link;
        $group_update_comment .= "\n\n";
        $group_update_comment .= 'Group Name: ' . $group_name;
        $group_update_comment .= "\n";
        $group_update_comment .= 'Location: ' . $group_location;
        $group_update_comment .= "\n";
        $group_update_comment .= 'Start Date: ' . $start_date;

        $group = [
            'parent_groups' => [ 'values' => [ [ 'value' => $parent_group ] ] ],
            'name' => $group_name,
            'start_date' => strtotime( $start_date ),
            'contact_address' => [ 'values' => [ [ 'value' => $group_location ] ] ],
            'notes' => [ $group_update_comment ],
        ];
        $group = DT_Posts::create_post( 'groups', $group, false, false );
        if ( is_wp_error( $group ) ){
            return;
        }
        $this->send_whatsapp_message( $params['To'], $params['From'], 'Group Created with number: ' . $group['ID'] );
    }

    public function process_text_update( $pieces, $link, $params ){
        /**
         * 0. group #
         * 1. text update
         */

        $group_id = (int) $pieces[0];
        $text_update = $pieces[1];

        $group_update_comment = 'Text Update Received from: ' . $link;
        $group_update_comment .= "\n\n";
        $group_update_comment .= $text_update;

        $group_update = [
            'notes' => [ $group_update_comment ],
        ];
        DT_Posts::update_post( 'groups', $group_id, $group_update, false, false );
        $this->send_whatsapp_message( $params['To'], $params['From'], 'Thank You' );
    }
}

Africa_DMM_Workflows::instance();
