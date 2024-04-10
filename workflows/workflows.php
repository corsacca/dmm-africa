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


    public function send_whatsapp_message( $to, $message ){
        $token = get_option( Disciple_Tools_Twilio_API::$option_twilio_token );
        $sid = get_option( Disciple_Tools_Twilio_API::$option_twilio_sid );

        $twilio = new Client( $sid, $token );

        $message = $twilio->messages
            ->create( $to,
                [
                    'from' => 'whatsapp:+14054496743',
                    'body' => $message,
                ]
            );
        dt_write_log( $message->sid );
    }

    public function dt_twilio_message_received( $type, $conversation_post_id, $params ){

        if ( $type !== 'whatsapp' ){
            return;
        }
        $comment = $params['Body'];

        $pieces = explode( ',', $comment );
        $conversation = DT_Posts::get_post( 'conversations', $conversation_post_id, true, false );
        $link = '[' . $params['From'] . '](' . $conversation['permalink'] . ')';

        if ( is_numeric( $pieces[0] ) && count( $pieces ) >= 10  ){
            $group = DT_Posts::get_post( 'groups', $pieces[0], true, false );
            if ( is_wp_error( $group ) ){
                $this->send_whatsapp_message( $params['From'], 'Group not found' );
                return;
            }
            $this->process_group_update( $pieces, $link, $params );
        } else if ( $pieces[0] === 'new' && count( $pieces ) >= 5 ){
            $this->process_new_group( $pieces, $link, $params );
        } else if ( $pieces[0] === 'help' ){
            $this->help_format( $params );
        } else {
            $this->send_whatsapp_message( $params['From'], 'Sorry, please try again. Send "help" for the expected message format' );
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
- Y/N church, 
- # in group who have started new group [3 people have started 3 groups = 3-3]
- location, 
- date started, 
- general update';
        $message .= "\n";
        $message .= "\n";
        $message .= 'Your update should look like:';
        $message .= "\n";
        $message .= "\n";
        $message .= '135, Simon, Jonah, 4, 6, 3, Y, 3-6, Buipe, 2023-04-04';
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
        $this->send_whatsapp_message( $params['From'], $message );
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
         * 7. # in group who have started new group [3 people have started 3 groups = 3-3]
         * 8. location
         * 9. date started
         * 10. general update
         *
         * example: 135, Simon, Jonah, 4, 6, 3, Y, 3-6, Buipe, 4-4-23
         */

        $group_id = (int) $pieces[0];
        $name_group_leader = $pieces[1];
        $name_of_coach = $pieces[2];
        $unbelievers = (int) $pieces[3];
        $baptized_believers = (int) $pieces[4];
        $accountability_group = (int) $pieces[5];
        $church = $pieces[6];
        $started_new_group = $pieces[7];
        $location = $pieces[8];
        $date_started = $pieces[9];
        $comment = $pieces[10] ?? '';

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
        $group_update_comment .= 'Started New Group: ' . $started_new_group;
        $group_update_comment .= "\n";
        $group_update_comment .= 'Location: ' . $location;
        $group_update_comment .= "\n";
        $group_update_comment .= 'Date Started: ' . $date_started;
        $group_update_comment .= "\n";
        $group_update_comment .= 'General Update: ' . $comment;


        $group_update = [
            'notes' => [ $group_update_comment ],
            'member_count' => $unbelievers + $baptized_believers,
        ];

        $tets = DT_Posts::update_post( 'groups', $group_id, $group_update, false, false );
        $this->send_whatsapp_message( $params['From'], 'Thank You' );

    }


    public function process_new_group($pieces, $link, $params){
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
            'parent_groups' => [ "values" => [ [ 'value' => $parent_group ] ] ],
            'name' => $group_name,
            'start_date' => strtotime( $start_date ),
            'notes' => [ $group_update_comment ],
        ];
        $group = DT_Posts::create_post( 'groups', $group, false, false );
        if ( is_wp_error( $group ) ){
            return;
        }
        $this->send_whatsapp_message( $params['From'], 'Group Created with number: ' . $group['ID'] );




    }
}

Africa_DMM_Workflows::instance();
