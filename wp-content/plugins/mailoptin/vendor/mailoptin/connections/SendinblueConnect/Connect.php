<?php

namespace MailOptin\SendinblueConnect;

use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractSendinblueConnect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'SendinblueConnect';

    public function __construct()
    {
        ConnectSettingsPage::get_instance();

        add_filter('mailoptin_registered_connections', array($this, 'register_connection'));

        parent::__construct();
    }

    public static function features_support($connection_service = '')
    {
        return [
            self::OPTIN_CAMPAIGN_SUPPORT,
            self::EMAIL_CAMPAIGN_SUPPORT,
            self::OPTIN_CUSTOM_FIELD_SUPPORT
        ];
    }

    /**
     * Register Constant Contact Connection.
     *
     * @param array $connections
     *
     * @return array
     */
    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('SendinBlue', 'mailoptin');

        return $connections;
    }

    /**
     * Replace placeholder tags with actual Sendinblue tags.
     *
     * {@inheritdoc}
     */
    public function replace_placeholder_tags($content, $type = 'html')
    {
        // https://help.sendinblue.com/hc/en-us/articles/209553645-Insert-default-header-and-footer-to-your-campaign
        $search = [
            '{{webversion}}',
            '{{unsubscribe}}'
        ];

        $replace = [
            '[MIRROR]',
            '[UNSUBSCRIBE]'
        ];

        $content = str_replace($search, $replace, $content);

        return $this->replace_footer_placeholder_tags($content);
    }

    /**
     * {@inherit_doc}
     *
     * Return array of email list
     *
     * @return mixed
     */
    public function get_email_list()
    {
        try {

            $response = $this->sendinblue_instance()->make_request('contacts/lists');

            // an array with list id as key and name as value.
            $lists_array = array();

            if ( ! isset($response['body']->lists)) {
                self::save_optin_error_log(json_encode($response['body']), 'sendinblue');

                return $lists_array;
            }

            $response = $response['body']->lists;
            if (is_array($response) && ! empty($response)) {
                foreach ($response as $list) {
                    $lists_array[$list->id] = $list->name;
                }
            }

            return $lists_array;

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'sendinblue');
        }
    }

    public function get_optin_fields($list_id = '')
    {
        try {

            $response = $this->sendinblue_instance()->make_request('contacts/attributes');

            if ( ! isset($response['body']->attributes)) {
                return self::save_optin_error_log(json_encode($response['body']), 'sendinblue');
            }

            $response = $response['body']->attributes;

            if (is_array($response) && ! empty($response)) {
                $custom_fields_array = [];
                foreach ($response as $customField) {
                    $attribute = $customField->name;

                    $firstname_key = apply_filters('mo_connections_sendinblue_firstname_key', 'FIRSTNAME');
                    $lastname_key  = apply_filters('mo_connections_sendinblue_lastname_key', 'LASTNAME');

                    if (in_array($attribute, ['BLACKLIST', 'CLICKERS', 'READERS', $firstname_key, $lastname_key])) continue;
                    $custom_fields_array[$attribute] = $attribute;
                }

                return $custom_fields_array;
            }

            return self::save_optin_error_log(json_encode($response['body']), 'sendinblue');

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'sendinblue');
        }
    }

    /**
     * @param int $email_campaign_id
     * @param int $campaign_log_id
     * @param string $subject
     * @param string $content_html
     * @param string $content_text
     *
     * @throws \Exception
     *
     * @return array
     */
    public function send_newsletter($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text)
    {
        return (new SendCampaign($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text))->send();
    }

    /**
     * @param string $email
     * @param string $name
     * @param string $list_id ID of email list to add subscriber to
     * @param mixed|null $extras
     *
     * @return mixed
     */
    public function subscribe($email, $name, $list_id, $extras = null)
    {
        return (new Subscription($email, $name, $list_id, $extras))->subscribe();
    }

    /**
     * Singleton poop.
     *
     * @return Connect|null
     */
    public static function get_instance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
}