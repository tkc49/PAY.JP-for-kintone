<?php
/**
 * Ht_Payjp_For_Kintone_Payment
 *
 * @package Payjp_For_Kintone
 */

/**
 * Ht_Payjp_For_Kintone_Payment
 */
class HT_Payjp_For_Kintone_Payment {

	/**
	 * 決済完了後にPAY.JP からリターンされる一意のID.
	 *
	 * @var string .
	 */
	private $payjp_charged_id;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wpcf7_before_send_mail', array( $this, 'payment_to_pay_jp' ), 10, 3 );
		add_filter( 'form_data_to_kintone_post_datas', array( $this, 'add_payjp_billing_id_to_kintone' ), 10, 3 );
	}

	/**
	 * PAY.JP へ決済する.
	 *
	 * @param WPCF7_ContactForm $contact_form .
	 * @param boolean           $abort .
	 * @param WPCF7_Submission  $submission .
	 */
	public function payment_to_pay_jp( $contact_form, &$abort, $submission ) {

		// 有効でない場合は何もせずにリターン.
		$payjpforkintone_setting_data = get_post_meta( $contact_form->id(), '_ht_payjpforkintone_setting_data', true );
		if ( 'enable' !== $payjpforkintone_setting_data['payjpforkintone-enabled'] ) {
			return;
		}

		if ( isset( $_POST['payjp-token'] ) && '' !== $_POST['payjp-token'] ) {

			$token = sanitize_text_field( wp_unslash( $_POST['payjp-token'] ) );

			$payjpforkintone_setting_data = get_post_meta(
				$contact_form->id(),
				'_ht_payjpforkintone_setting_data',
				true
			);

			if ( isset( $payjpforkintone_setting_data['live-enabled'] ) && 'enable' === $payjpforkintone_setting_data['live-enabled'] ) {
				// Live.
				$secret_key = get_option( 'ht_pay_jp_for_kintone_live_secret_key' );
			} else {
				$secret_key = get_option( 'ht_pay_jp_for_kintone_test_secret_key' );
			}

			$amount_cf7_mailtag = $payjpforkintone_setting_data['amount-cf7-mailtag'];
			$post_data          = $submission->get_posted_data();
			$amount             = $post_data[ $amount_cf7_mailtag ];

			\Payjp\Payjp::setApiKey( $secret_key );
			try {
				$charge = \Payjp\Charge::create(
					[
						'card'     => $token,
						'amount'   => $amount,
						'currency' => 'jpy',
					]
				);

				// IDを保存する.
				$this->payjp_charged_id = $charge->id;

				$submited['posted_data']                     = $submission->get_posted_data();
				$submited['posted_data']['payjp-charged-id'] = $charge->id;

				$mail = $contact_form->prop( 'mail' );

				$mail['body'] = str_replace(
					'[payjp-charged-id]',
					$submited['posted_data']['payjp-charged-id'],
					$mail['body']
				);

				$mail2         = $contact_form->prop( 'mail_2' );
				$mail2['body'] = str_replace(
					'[payjp-charged-id]',
					$submited['posted_data']['payjp-charged-id'],
					$mail2['body']
				);

				$contact_form->set_properties(
					array(
						'mail'   => $mail,
						'mail_2' => $mail2,
					)
				);

			} catch ( \Payjp\Error\InvalidRequest $e ) {

				$abort = true;
				$submission->set_response( $contact_form->filter_message( $e->getMessage() ) );
				$this->send_error_mail( $contact_form, $e );
			}
		} else {
			// Error.
			$abort = true;
			$submission->set_response(
				$contact_form->filter_message(
					__(
						'Failed to get credit card information',
						'payjp-for-kintone'
					)
				)
			);
		}

	}

	/**
	 * エラーメール送信.
	 *
	 * @param WPCF7_ContactForm           $contact_form .
	 * @param \Payjp\Error\InvalidRequest $e .
	 */
	private function send_error_mail( $contact_form, $e ) {

		$kintone_setting_data = $contact_form->prop( 'kintone_setting_data' );

		if ( empty( $kintone_setting_data ) ) {
			return;
		}

		$error_msg = $e->getMessage();

		$email_address_to_send_kintone_registration_error = $kintone_setting_data['email_address_to_send_kintone_registration_error'];

		if ( $email_address_to_send_kintone_registration_error ) {
			$to = $email_address_to_send_kintone_registration_error;
		} else {
			$to = get_option( 'admin_email' );
		}

		$subject = esc_html__( 'Error : PAY.JP Payment', 'payjp-for-kintone' );
		$body    = $error_msg;
		wp_mail( $to, $subject, $body );

	}

	/**
	 * PAY.JPからリターンされる課金IDをkintoneへ保存する.
	 *
	 * @param array  $datas kintoneへ登録するデータ.
	 * @param int    $appid kintoneへ登録するアプリ番号.
	 * @param string $unique_key ユニークキー（アップデータするときに使う）.
	 *
	 * @return array
	 */
	public function add_payjp_billing_id_to_kintone( $datas, $appid, $unique_key ) {

		$contact_form                 = WPCF7_ContactForm::get_current();
		$payjpforkintone_setting_data = get_post_meta( $contact_form->id(), '_ht_payjpforkintone_setting_data', true );

		// 有効ではない場合は、何もせずにリターン.
		if ( ! isset( $payjpforkintone_setting_data['kintone-enabled'] ) ) {
			return $datas;
		}
		if ( 'enable' !== $payjpforkintone_setting_data['kintone-enabled'] ) {
			return $datas;
		}

		$kintone_fieldcode_for_payjp_billing_id = $payjpforkintone_setting_data['kintone-fieldcode-for-payjp-billing-id'];

		if ( empty( $kintone_fieldcode_for_payjp_billing_id ) ) {
			return $datas;
		}

		$add_data = array();

		$add_data[ $kintone_fieldcode_for_payjp_billing_id ] = array( 'value' => $this->payjp_charged_id );

		$datas = array_merge( $datas, $add_data );

		return $datas;

	}

}
