<?php
namespace AvaGF;

defined( 'ABSPATH' ) || exit;

/** Native Gravity Forms feed UI; delivery safeguards live in Delivery. */
final class AddOn extends \GFFeedAddOn {
    protected $_version = AVA_GF_VERSION;
    protected $_min_gravityforms_version = '2.8';
    protected $_slug = 'ava-gravityforms-plugin';
    protected $_path = 'ava-GravityForms-plugin/ava-gravityforms-plugin.php';
    protected $_full_path = AVA_GF_FILE;
    protected $_title = 'Ava Gravity Forms';
    protected $_short_title = 'Ava SMS';
    protected $_capabilities_form_settings = 'manage_options';
    protected $_capabilities_settings_page = 'manage_options';
    protected $_capabilities_uninstall = 'manage_options';
    public $_async_feed_processing = false;

    private static $_instance = null;

    public static function get_instance() {
        if ( null === self::$_instance ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function feed_settings_fields() {
        return array(
            array(
                'title' => esc_html__( 'اعلان پیامکی آوا', 'ava-gravityforms-plugin' ),
                'description' => esc_html__( 'هر درخواست ابتدا برای بررسی در پنل آوا ثبت می‌شود. ارسال به تأیید و آغاز صریح در آوا نیاز دارد؛ پذیرش درخواست به معنی تحویل پیامک نیست. اتصال را در تنظیمات وردپرس ← Ava Gravity Forms فعال کنید.', 'ava-gravityforms-plugin' ),
                'fields' => array(
                    array(
                        'name' => 'feedName',
                        'label' => esc_html__( 'نام اعلان', 'ava-gravityforms-plugin' ),
                        'type' => 'text',
                        'required' => true,
                        'class' => 'medium',
                    ),
                    array(
                        'name' => 'recipientMode',
                        'label' => esc_html__( 'گیرنده', 'ava-gravityforms-plugin' ),
                        'type' => 'select',
                        'required' => true,
                        'default_value' => 'field',
                        'choices' => array(
                            array( 'label' => esc_html__( 'موبایل واردشده در فرم', 'ava-gravityforms-plugin' ), 'value' => 'field' ),
                            array( 'label' => esc_html__( 'شماره‌های ثابت مدیر', 'ava-gravityforms-plugin' ), 'value' => 'fixed' ),
                        ),
                    ),
                    array(
                        'name' => 'phoneField',
                        'label' => esc_html__( 'فیلد موبایل', 'ava-gravityforms-plugin' ),
                        'type' => 'field_select',
                        'required' => true,
                        'args' => array( 'input_types' => array( 'phone', 'text' ) ),
                        'dependency' => array( 'field' => 'recipientMode', 'values' => array( 'field' ), 'live' => true ),
                        'tooltip' => esc_html__( 'یک فیلد تلفن یا متن تک‌خطی با شماره موبایل ایران انتخاب کنید. برای فیلد تلفن، قالب بین‌المللی را انتخاب کنید.', 'ava-gravityforms-plugin' ),
                    ),
                    array(
                        'name' => 'fixedPhones',
                        'label' => esc_html__( 'موبایل‌های مدیر', 'ava-gravityforms-plugin' ),
                        'type' => 'textarea',
                        'required' => true,
                        'class' => 'medium',
                        'dependency' => array( 'field' => 'recipientMode', 'values' => array( 'fixed' ), 'live' => true ),
                        'tooltip' => esc_html__( 'حداکثر پنج موبایل ایران؛ هر شماره در یک خط یا جداشده با ویرگول. شماره‌های تکراری یک‌بار پردازش می‌شوند. برچسب‌های فرم در این بخش پشتیبانی نمی‌شوند.', 'ava-gravityforms-plugin' ),
                    ),
                    array(
                        'name' => 'message',
                        'label' => esc_html__( 'متن پیامک', 'ava-gravityforms-plugin' ),
                        'type' => 'textarea',
                        'required' => true,
                        'class' => 'large merge-tag-support mt-position-right',
                        'tooltip' => esc_html__( 'از برچسب‌های ادغام Gravity Forms برای درج مقدار فیلدها استفاده کنید. متن نهایی باید ۱ تا ۱۰۰۰ نویسه باشد. اطلاعات حساس را در پیامک قرار ندهید.', 'ava-gravityforms-plugin' ),
                    ),
                    array(
                        'name' => 'feed_condition',
                        'label' => esc_html__( 'شرط ثبت اعلان', 'ava-gravityforms-plugin' ),
                        'type' => 'feed_condition',
                        'checkbox_label' => esc_html__( 'فعال‌کردن شرط', 'ava-gravityforms-plugin' ),
                        'instructions' => esc_html__( 'درخواست پیامک را ثبت کن اگر', 'ava-gravityforms-plugin' ),
                    ),
                ),
            ),
        );
    }

    public function feed_list_columns() {
        return array(
            'feedName' => esc_html__( 'نام اعلان', 'ava-gravityforms-plugin' ),
            'recipientMode' => esc_html__( 'گیرنده', 'ava-gravityforms-plugin' ),
        );
    }

    public function get_column_value_recipientMode( $feed ) {
        $mode = isset( $feed['meta']['recipientMode'] ) ? $feed['meta']['recipientMode'] : '';
        return 'fixed' === $mode
            ? esc_html__( 'شماره‌های ثابت مدیر', 'ava-gravityforms-plugin' )
            : esc_html__( 'موبایل واردشده در فرم', 'ava-gravityforms-plugin' );
    }

    public function can_duplicate_feed( $feed_id = null ) {
        return true;
    }

    /** Never log form values or provider response bodies. */
    public function process_feed( $feed, $entry, $form ) {
        $result = Delivery::process( $feed, $entry, $form );
        $entry_id = isset( $entry['id'] ) ? absint( $entry['id'] ) : 0;
        if ( null === $result || ! $entry_id ) {
            return $entry;
        }
        $feed_id = isset( $feed['id'] ) ? absint( $feed['id'] ) : 0;
        if ( is_wp_error( $result ) ) {
            $note = sprintf(
                __( 'اعلان آوا شمارهٔ %1$d ثبت نشد: %2$s', 'ava-gravityforms-plugin' ),
                $feed_id,
                wp_strip_all_tags( $result->get_error_message() )
            );
            $this->add_note( $entry_id, esc_html( $note ), 'error' );
            return $result;
        }
        $lines = array( sprintf( __( 'نتیجهٔ درخواست اعلان آوا شمارهٔ %d:', 'ava-gravityforms-plugin' ), $feed_id ) );
        $has_error = false;
        foreach ( $result as $index => $summary ) {
            $status = isset( $summary['status'] ) && is_string( $summary['status'] ) ? $summary['status'] : 'unknown';
            $label = Delivery::status_label( $summary );
            if ( in_array( $status, array( 'error', 'unknown', 'rejected', 'blocked', 'cancelled', 'undelivered', 'expired' ), true ) ) {
                $has_error = true;
            }
            $line = sprintf( __( 'گیرندهٔ %1$d: %2$s', 'ava-gravityforms-plugin' ), $index + 1, $label );
            if ( isset( $summary['id'] ) && is_string( $summary['id'] ) && preg_match( '/^[A-Za-z0-9_-]{1,100}$/D', $summary['id'] ) ) {
                $line .= ' (' . __( 'شناسه', 'ava-gravityforms-plugin' ) . ': ' . $summary['id'] . ')';
            }
            $lines[] = $line;
        }
        $lines[] = __( 'پذیرش درخواست به معنی تحویل نیست. وضعیت جاری را از گزارش آوا بررسی کنید. درخواست‌های نامشخص خودکار تکرار نمی‌شوند.', 'ava-gravityforms-plugin' );
        $this->add_note( $entry_id, esc_html( implode( "\n", $lines ) ), $has_error ? 'error' : 'note' );
        return $entry;
    }

    /** Labels are local constants; unknown upstream values are never echoed. */
    public static function status_label( $status ) {
        $labels = array(
            'pending' => __( 'در انتظار بررسی', 'ava-gravityforms-plugin' ),
            'sending' => __( 'در حال پردازش ارسال', 'ava-gravityforms-plugin' ),
            'unknown' => __( 'وضعیت نامشخص؛ گزارش آوا را بررسی کنید', 'ava-gravityforms-plugin' ),
            'accepted' => __( 'پذیرفته‌شده؛ تحویل تأیید نشده', 'ava-gravityforms-plugin' ),
            'sent' => __( 'ارسال‌شده؛ تحویل تأیید نشده', 'ava-gravityforms-plugin' ),
            'delivered' => __( 'تحویل طبق گزارش اپراتور', 'ava-gravityforms-plugin' ),
            'undelivered' => __( 'تحویل‌نشده طبق گزارش اپراتور', 'ava-gravityforms-plugin' ),
            'rejected' => __( 'ردشده', 'ava-gravityforms-plugin' ),
            'blocked' => __( 'مسدودشده', 'ava-gravityforms-plugin' ),
            'cancelled' => __( 'لغوشده', 'ava-gravityforms-plugin' ),
            'simulated' => __( 'شبیه‌سازی‌شده؛ ارسال واقعی انجام نشده', 'ava-gravityforms-plugin' ),
            'expired' => __( 'منقضی‌شده', 'ava-gravityforms-plugin' ),
            'error' => __( 'درخواست ثبت نشد', 'ava-gravityforms-plugin' ),
        );
        return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['unknown'];
    }
}
