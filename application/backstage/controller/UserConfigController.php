<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageConfigBuilder;

class UserConfigController extends BackstageController{

    public function index(){

        $admin_config = new BackstageConfigBuilder();
        $data = $admin_config->handleConfig();

        empty($data['OPEN_QUICK_LOGIN']) && $data['OPEN_QUICK_LOGIN'] = 0;

        empty($data['LOGIN_SWITCH']) && $data['LOGIN_SWITCH'] = 'username';



        $addons = \think\Hook::get('sms');
        $opt = ['none' => lang('_NONE_')];
        foreach ($addons as $name) {
            if (class_exists($name)) {
                $class = new $name();
                $config = $class->getConfig();
                if ($config['switch']) {
                    $opt[$class->info['name']] = $class->info['title'];
                }
            }
        }

        $admin_config->title(lang('_USER_CONFIGURATION_'))->data($data)
            ->keyCheckBox('REG_SWITCH', lang('_REGISTRATION_SWITCH_'), lang('_THE_REGISTRATION_OPTION_THAT_ALLOWS_THE_USE_OF_THE_REGISTRATION_IS_CLOSED_'), [ 'email' => lang('_MAILBOX_'), 'mobile' => lang('_MOBILE_PHONE_')])
            ->keyRadio('EMAIL_VERIFY_TYPE', lang('_MAILBOX_VERIFICATION_TYPE_'), lang('_TYPE_MAILBOX_VERIFICATION_'), [0 => lang('_NOT_VERIFIED_'), 1 => lang('_POST_REGISTRATION_ACTIVATION_MAIL_'), 2 => lang('_EMAIL_VERIFY_SEND_BEFORE_REG_')])
            ->keyRadio('MOBILE_VERIFY_TYPE', lang('_MOBILE_VERIFICATION_TYPE_'), lang('_TYPE_OF_CELL_PHONE_VERIFICATION_'), [0 => lang('_NOT_VERIFIED_'), 1 => lang('_REGISTER_BEFORE_SENDING_A_VALIDATION_MESSAGE_')])

            ->keyEditor('REG_EMAIL_VERIFY', lang('_MAILBOX_VERIFICATION_TEMPLATE_'), lang('_PLEASE_EMAIL_VERIFY_'),'all')
            ->keyEditor('REG_EMAIL_ACTIVATE', lang('_MAILBOX_ACTIVATION_TEMPLATE_'), lang('_PLEASE_USER_ACTIVE_'))

            ->keySelect('SMS_HOOK', lang('_SMS_SENDING_SERVICE_PROVIDER_'), lang('_SMS_SEND_SERVICE_PROVIDERS_NEED_TO_INSTALL_THE_PLUG-IN_'), $opt)
            ->keyText('SMS_RESEND', lang('_THE_MESSAGE_RETRANSMISSION_TIME_'), lang('_THE_MESSAGE_RETRANSMISSION_TIME_'))
            ->keyText('SMS_UID', lang('_SMS_PLATFORM_ACCOUNT_NUMBER_'), lang('_SMS_PLATFORM_ACCOUNT_NUMBER_'))
            ->keyText('SMS_PWD', lang('_SMS_PLATFORM_PASSWORD_'), lang('_SMS_PLATFORM_PASSWORD_'))
            ->keyTextArea('SMS_CONTENT', lang('_MESSAGE_CONTENT_'), lang('_MSG_VERICODE_ACCOUNT_'))

            ->keyInteger('NICKNAME_MIN_LENGTH', lang('_NICKNAME_LENGTH_MINIMUM_'))->keyDefault('NICKNAME_MIN_LENGTH',2)
            ->keyInteger('NICKNAME_MAX_LENGTH', lang('_NICKNAME_LENGTH_MAXIMUM_'))->keyDefault('NICKNAME_MAX_LENGTH',32)
            ->keyInteger('USERNAME_MIN_LENGTH', lang('_USERNAME_LENGTH_MINIMUM_'))->keyDefault('USERNAME_MIN_LENGTH',2)
            ->keyInteger('USERNAME_MAX_LENGTH', lang('_USERNAME_LENGTH_MAXIMUM_'))->keyDefault('USERNAME_MAX_LENGTH',32)


            ->keyRadio('OPEN_QUICK_LOGIN',lang('_QUICK_LOGIN_'),lang('_BY_DEFAULT_AFTER_THE_USER_IS_LOGGED_IN_THE_USER_IS_LOGGED_IN_'), [0 => lang('_OFF_'), 1 => lang('_OPEN_')])


            ->keyCheckBox('LOGIN_SWITCH', lang('_LOGIN_PROMPT_SWITCH_'), lang('_JUST_THE_TIP_OF_THE_LOGIN_BOX_'), ['email' => lang('_MAILBOX_'), 'mobile' => lang('_MOBILE_PHONE_')])

            ->group(lang('_REGISTER_CONFIGURATION_'), 'REG_SWITCH,EMAIL_VERIFY_TYPE,MOBILE_VERIFY_TYPE')
            ->group(lang('_LOGIN_CONFIGURATION_'), 'OPEN_QUICK_LOGIN,LOGIN_SWITCH')
            ->group(lang('_MAILBOX_VERIFICATION_TEMPLATE_'), 'REG_EMAIL_VERIFY')
            ->group(lang('_MAILBOX_ACTIVATION_TEMPLATE_'), 'REG_EMAIL_ACTIVATE')
            ->group(lang('_SMS_CONFIGURATION_'), 'SMS_HTTP,SMS_UID,SMS_PWD,SMS_CONTENT,SMS_HOOK,SMS_RESEND')
            ->group(lang('_BASIC_SETTINGS_'), 'NICKNAME_MIN_LENGTH,NICKNAME_MAX_LENGTH,USERNAME_MIN_LENGTH,USERNAME_MAX_LENGTH')
            ->buttonSubmit('', lang('_SAVE_'))
            ->keyDefault('REG_EMAIL_VERIFY',lang('_VERICODE_ACCOUNT_').lang('_PERIOD_'))
            ->keyDefault('REG_EMAIL_ACTIVATE',lang('_LINK_ACTIVE_IS_'))
            ->keyDefault('SMS_CONTENT',lang('_VERICODE_ACCOUNT_'))
            ->keyDefault('SMS_RESEND','60');
        return $admin_config->show();
    }
}