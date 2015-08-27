<?php

class Users extends model
{
    private static $instance = null;
    protected $userEmailSender;

    public function __construct()
    {
        parent::__construct();
        $this->userEmailSender = userEmailSender::init();
    }

    /**
     * Подписывался ли пользаватель ранее (true - да, false - нет)
     * @param $email
     * @return boolean
     */
    private function isUserSubscribed($email)
    {
        $this->db->select();
        $this->db->tables('`subscribe`');
        $this->db->fields();
        $this->db->where('subscribe_email = "' . $email . '"');

        $result = $this->db->execute(true);

        if ($result) {
            $this->errors[] = array(
                'type' => 'error',
                'code' => 'already_subscribed',
                'namespace' => 'subscribe_user',
                'text' => 'User has already subscribed'
            );

            return true;
        }

        return false;
    }

    /**
     * Зарегистрирован ли пользователь с такой почтой (array - да, false - нет)
     * @param $email
     * @return boolean | array
     */
    private function isUserExist($email)
    {
        $this->db->select();
        $this->db->tables('`users`');
        $this->db->fields();
        $this->db->where('users_email = "' . $email . '"');

        $result = $this->db->execute(true);

        if ($result) {
            return $result[0];
        }

        return false;
    }

    /**
     * Зарегистрирован ли пользователь с таким соц. профилем
     * @param $network
     * @param $uid
     * @return boolean | array
     */
    private function isUserExistSocialUid($network, $uid)
    {
        $this->db->select();
        $this->db->tables('`users`');
        $this->db->fields();
        $this->db->where('users_social_uid = "' . $uid . '" and users_social_network = "' . $network . '"');

        $result = $this->db->execute(true);

        if ($result) {
            return $result[0];
        }

        return false;
    }

    /**
     * Кодировка пароля по алгоритму md5
     * @param string $password
     * @return string $password
     */
    private function encryptionPassword($password)
    {
        return md5($password);
    }

    /**
     * Добавить пользователя
     * @param array
     * @return boolean
     */
    private function addUser($params)
    {
        if (isset($params['users_repassword'])) {
            unset($params['users_repassword']);
        }

        if (!isset($params['users_registration_date'])) {
            $params['users_registration_date'] = date("Y-m-d H:i:s");
        }

        if (!isset($params['users_active'])) {
            $params['users_active'] = 0;
        }

        if (!isset($params['users_email_confirm'])) {
            $params['users_email_confirm'] = 0;
        }

        $this->db->insert();
        $this->db->tables('`users`');
        $this->db->fields($params);
        $result = $this->db->execute(true);

        if (!$result) {
            $this->errors[] = array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Insert fails to execute'
            );

            return false;
        }

        return true;
    }

    /**
     * Проверить является ли пользователь другом участника
     * @return boolean
     */
    private function isUserFriend($friendUid)
    {
        if (!isset($_SESSION['friends']) || !isset($_SESSION['friends'][$friendUid])) {
            return false;
        }

        return true;
    }

    /**
     * Проверям может ли пользователь шерить проект
     * @return boolean
     */
    public function isShareAvailable()
    {
        $this->db->select();
        $this->db->tables('`shares`');
        $this->db->fields();
        $this->db->where('shares_users_id = "' . $_SESSION['auth_users']['users_id'] . '" and shares_users_social_network = "' . $_SESSION['auth_users']['users_social_network'] . '"');
        $this->db->order('shares_date', 'DESC');
        $dataShare = $this->db->execute(true);

        if (isset($dataShare[0])) {
            if (Functions::countDaysDiffCurrTime($dataShare[0]['shares_date']) < 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Проверям может ли пользователь вступить в группу
     * @return boolean
     */
    public function isJoinGroupAvailable()
    {
        $this->db->select();
        $this->db->tables('`social_group`');
        $this->db->fields();
        $this->db->where('social_group_users_id = "' . $_SESSION['auth_users']['users_id'] . '" and social_group_users_social_network = "' . $_SESSION['auth_users']['users_social_network'] . '"');
        $dataInviteGroup = $this->db->execute(true);

        if (isset($dataInviteGroup[0])) {
            return false;
        }

        return true;
    }

    /**
     * Проверям может ли пользователь пригласить данного друга
     * @param $invite_friend_social_uid
     * @return boolean
     */
    public function isInviteFriendAvailable($invite_friend_social_uid)
    {
        $this->db->select();
        $this->db->tables('`invite`');
        $this->db->fields();
        $this->db->where('invite_users_id = "' . $_SESSION['auth_users']['users_id'] . '" and invite_friend_social_uid = "' . $invite_friend_social_uid . '" and invite_friend_social_network = "' . $_SESSION['auth_users']['users_social_network'] . '"');
        $this->db->order('invite_date', 'DESC');
        $dataInviteFriend = $this->db->execute(true);

        if (isset($dataInviteFriend[0])) {
            if (Functions::countDaysDiffCurrTime($dataInviteFriend[0]['invite_date']) < 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Обновить данные пользователя
     * @param $email
     * @param $params
     * @return boolean
     */
    public function updateUser($email, $params)
    {
        $this->db->update();
        $this->db->tables('`users`');
        $this->db->fields($params);
        $this->db->where('users_email = "' . $email . '"');
        $result = $this->db->execute(true);

        if (!$result) {
            $this->errors[] = array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Update fails to execute'
            );

            return false;
        }

        return true;
    }

    /**
     * Обновить данные пользователя по соц. профилю
     * @param $params
     * @return boolean
     */
    public function updateUserSocialUid($params)
    {
        $this->db->update();
        $this->db->tables('`users`');
        $this->db->fields($params);
        $this->db->where('users_social_uid = "' . $params['users_social_uid'] . '" and users_social_network = "' . $params['users_social_network'] . '"');
        $result = $this->db->execute(true);

        if (!$result) {
            $this->errors[] = array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Update fails to execute'
            );

            return false;
        }

        return true;
    }

    /**
     * Проверка пары логин-пароль (true - да, false - нет)
     * @param $email
     * @param $password
     * @return boolean or array
     */
    private function checkAuthorizationLoginPassword($email, $password)
    {
        $this->db->select();
        $this->db->tables('`users`');
        $this->db->fields();
        $this->db->where('users_email = "' . $email . '" and users_password = "' . $password . '"');
        $result = $this->db->execute(true);

        if (!$result) {
            return false;
        }

        return $result[0];
    }

    /**
     * Очистить сессию авторизованного пользователя
     */
    private function destroyUserSession()
    {
        if (isset($_SESSION['auth_users'])) {
            unset($_SESSION['auth_users']);
            unset($_SESSION['ok']);
            unset($_SESSION['friends']);
        }
    }

    /**
     * Добавляем в сессию данные пользователя
     */
    private function addUserDataInSession($usersID, $usersName, $usersAvatar, $usersEmail, $competitionID = null, $competitionRating = null, $userSocialUid = null, $userSocialNetwork = null)
    {
        $_SESSION['auth_users']['users_id'] = $usersID;
        $_SESSION['auth_users']['users_name'] = $usersName;
        $_SESSION['auth_users']['users_avatar'] = $usersAvatar;
        $_SESSION['auth_users']['users_email'] = $usersEmail;
        $_SESSION['auth_users']['competition_id'] = $competitionID;
        $_SESSION['auth_users']['all_rating'] = $competitionRating;
        $_SESSION['auth_users']['users_social_uid'] = $userSocialUid;
        $_SESSION['auth_users']['users_social_network'] = $userSocialNetwork;
    }

    /**
     * Прислать письмо для подтверждения
     * @param $email
     * @return boolean
     */
    private function sendConfirmEmail($email)
    {
        $verification_key = Functions::generate_password($length = 6);
        $params['registration_confirm_verification'] = $verification_key;
        $params['registration_confirm_users_email'] = $email;

        $this->db->insert();
        $this->db->tables('`registration_confirm`');
        $this->db->fields($params);
        $result = $this->db->execute(true);

        if (!$result) {
            $this->errors[] = array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Insert fails to execute'
            );

            return false;
        }

        $result = $this->userEmailSender->sendConfirmEmail($verification_key, $email);

        if (!$result) {
            $this->errors[] = array(
                'type' => 'error',
                'code' => 'sending_email_failed',
                'namespace' => 'default',
                'text' => 'Sending email failed'
            );

            return false;
        }

        return true;
    }

    /**
     * Прислать письмо с новым паролем
     * @param $email
     * @param $new_password
     * @return boolean
     */
    private function sendEmailWithNewPassword($email, $new_password)
    {
        $result = $this->userEmailSender->sendEmailWithNewPassword($email, $new_password);

        if (!$result) {
            $this->errors[] = array(
                'type' => 'error',
                'code' => 'sending_email_failed',
                'namespace' => 'default',
                'text' => 'Sending email failed'
            );

            return false;
        }

        return true;
    }

    /**
     * Подписаться на рассылку
     * @param array
     * @return array
     */
    public function subscribeUser($email)
    {
        $needleCheck = array(
            'email' => true
        );

        if (!$this->dataHandler->isValidParams(array('email' => $email), $needleCheck)) {
            $response = $this->dataHandler->getLastDataHandlerError();
            return $response;
        }

        if ($this->isUserSubscribed($email)) {
            $response = $this->getLastError();
            return $response;
        }

        $this->db->insert();
        $this->db->tables('`subscribe`');
        $this->db->fields(
            array(
                'subscribe_email' => $email
            )
        );
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Insert fails to execute'
            );
        }

        return array(
            'type' => 'success',
            'code' => 'subscribe_user_true',
            'namespace' => 'subscribe_user',
            'text' => 'User was subscribed'
        );
    }

    /**
     * Регистрация пользователя
     * @param array
     * @return array
     */
    public function registrationUser($parameters)
    {
        $needleCheck = array(
            'name' => true,
            'email' => true,
            'lastname' => true,
            'firstname' => true,
            'birthday' => true,
            'address' => true,
            'phone' => true,
            'password' => true,
            'repassword' => true
        );

        if (!$this->dataHandler->isValidParams($parameters, $needleCheck)) {
            $response = $this->dataHandler->getLastDataHandlerError();
            return $response;
        }

        if ($this->isUserExist($parameters['email'])) {
            return array(
                'type' => 'error',
                'code' => 'already_registered',
                'namespace' => 'registration_user',
                'text' => 'User with this email has already registered'
            );
        }

        $parameters['password'] = $this->encryptionPassword($parameters['password']);
        $parameters['email_confirm'] = 0;
        $params = $this->dataHandler->bindParamsWithType($parameters, 'users');

        if (!$this->addUser($params)) {
            $response = $this->getLastError();
            return $response;
        }

        if (!$this->sendConfirmEmail($parameters['email'])) {
            $response = $this->getLastError();
            return $response;
        }

        return array(
            'type' => 'success',
            'code' => 'registration_user_true',
            'namespace' => 'registration_user',
            'text' => 'User was registered'
        );
    }

    /**
     * Авторизация пользователя
     * @param array
     * @return array
     */
    public function authorizationUser($parameters)
    {
        $needleCheck = array(
            'email' => true,
            'password' => true
        );

        if (!$this->dataHandler->isValidParams($parameters, $needleCheck)) {
            $response = $this->dataHandler->getLastDataHandlerError();
            return $response;
        }

        //Проверка логина-пароля
        $userData = $this->checkAuthorizationLoginPassword($parameters['email'], $this->encryptionPassword($parameters['password']));

        //Ошибка пары логин-пароль
        if (!$userData) {
            return array(
                'type' => 'error',
                'code' => 'user_login_password_incorrect',
                'namespace' => 'authorization_user',
                'text' => 'Incorrect login or password'
            );
        }

        //Все верно
        if ($userData['users_email_confirm'] == 1) {
            $this->destroyUserSession();
            $this->addUserDataInSession($userData['users_id'], $userData['users_name'], $userData['users_avatar'], $userData['users_email']);

            return array(
                'type' => 'success',
                'code' => 'authorization_user_true',
                'namespace' => 'authorization_user',
                'text' => 'User was authorized'
            );
        }

        if (!$this->sendConfirmEmail($parameters['users_email'])) {
            $response = $this->getLastError();
            return $response;
        }

        return array(
            'type' => 'error',
            'code' => 'user_not_verified',
            'namespace' => 'authorization_user',
            'text' => 'User was not verified. Letter sent to confirm'
        );
    }

    /**
     * Авторизация пользователя через соц. сеть
     * @param array
     * @return array
     */
    public function socialAuthorizationUsers($params, $competition)
    {
        if (empty($params['email'])) {
            unset($params['email']);
            $email = null;
        }

        if (isset($params['callback_url'])) {
            $callbackUrl = $params['callback_url'];
            unset($params['callback_url']);
        } else {
            $callbackUrl = '/';
        }

        // Есть ли у нас в базе пользователь с такой соц. сетью?
        $userData = $this->isUserExistSocialUid($params['social_network'], $params['social_uid']);

        if ($userData) {
            if (!empty($userData['users_email'])) {
                $email = $userData['users_email'];
            }

            // Да есть, обновляем данные
            $userID = $userData['users_id'];
            $params = $this->dataHandler->bindParamsWithType($params, 'users');
            $result = $this->updateUserSocialUid($params);
            if (!$result) {
                $response = $this->getLastError();
                return $response;
            }

            $competitionData = $competition->getCompetitionItemByUserId($userID);
            if ($competitionData) {
                $competitionID = $competitionData['competition_member_id'];
                $competitionRating = $competitionData['all_rating'];
            }
        } else {
            // Нет, у нас нет такого пользователя, добавляем его в нашу базу
            $userID = $this->getAutoincrement('users');
            $params['active'] = 1;

            if (!empty($params['email'])) {
                $email = $params['email'];
            }

            $params = $this->dataHandler->bindParamsWithType($params, 'users');
            $result = $this->addUser($params);

            if (!$result) {
                $response = $this->getLastError();
                return $response;
            }

            // Сразу добавляем пользователя в конкурс
            if (!$competition->isCompetitionExist($userID)) {
                $params_competition['competition_member_item_id'] = $userID;
                $competitionID = $this->getAutoincrement('users');
                $competitionRating = 0;
                $this->db->insert();
                $this->db->tables('`competition_member`');
                $this->db->fields(array(
                    'competition_member_users_id' => $userID,
                    'competition_member_active' => 1
                ));
                $this->db->execute(true);
            }
        }

        $this->destroyUserSession();
        $this->addUserDataInSession($userID, $params['users_name'], $params['users_avatar'], $email, $competitionID, $competitionRating, $params['users_social_uid'], $params['users_social_network']);

        if (empty($email)) {
            return array(
                'type' => 'error',
                'code' => 'email_empty',
                'namespace' => 'social_authorization_user',
                'parameters' =>
                    array(
                        'callback_url' => $callbackUrl
                    ),
                'text' => 'User email is empty'
            );
        }

        return array(
            'type' => 'success',
            'code' => 'social_authorization_user_true',
            'namespace' => 'social_authorization_user',
            'parameters' =>
                array(
                    'callback_url' => $callbackUrl
                ),
            'text' => 'User was registered'
        );
    }

    /**
     * Авторизация пользователя через одноклассники
     * @param array $parameters
     * @return template
     */
    public function odnoklassnikiAuthorizationUsers($code, $redirectUrl)
    {
        if (!empty($code)) {
            $params = array(
                'code' => $code,
                'redirect_uri' => happy::$p['root'] . $redirectUrl,
                'grant_type' => 'authorization_code',
                'client_id' => happy::$config['client_id'],
                'client_secret' => happy::$config['client_secret']
            );

            $url = 'https://api.odnoklassniki.ru/oauth/token.do';

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, urldecode(http_build_query($params)));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($curl);
            curl_close($curl);

            $tokenInfo = json_decode($result, true);

            if (isset($tokenInfo['access_token'])) {
                $sign = md5("application_key=" . happy::$config['public_key'] . "fields=uid,name,first_name,last_name,gender,pic_3,birthdayformat=jsonmethod=users.getCurrentUser" . md5("{$tokenInfo['access_token']}" . happy::$config['client_secret']));

                $_SESSION['ok']['access_token'] = $tokenInfo['access_token'];

                $params = array(
                    'method' => 'users.getCurrentUser',
                    'access_token' => $tokenInfo['access_token'],
                    'application_key' => happy::$config['public_key'],
                    'format' => 'json',
                    'sig' => $sign,
                    'fields' => 'uid,name,first_name,last_name,gender,pic_3,birthday'
                );

                $userInfo = json_decode(file_get_contents('http://api.odnoklassniki.ru/fb.do' . '?' . urldecode(http_build_query($params))), true);

                if (isset($userInfo['uid'])) {
                    $dataUsers['social_uid'] = $userInfo['uid'];
                    $dataUsers['name'] = $userInfo['first_name'] . ' ' . $userInfo['last_name'];
                    $dataUsers['firstname'] = $userInfo['first_name'];
                    $dataUsers['lastname'] = $userInfo['last_name'];
                    $dataUsers['avatar'] = $userInfo['pic_3'];
                    $dataUsers['social_uid'] = $userInfo['uid'];
                    $dataUsers['social_profile'] = 'http://odnoklassniki.ru/profile/' . $userInfo['uid'];
                    $dataUsers['social_network'] = 'ok';
                    $dataUsers['gender'] = $userInfo['gender'];
                    $dataUsers['birthday'] = $userInfo['birthday'];

                    $competition = competition_model::init();
                    $this->socialAuthorizationUsers($dataUsers, $competition);
                    return true;
                }
            }
        } else {
            return false;
        }
    }

    /**
     * Добавить email
     * @param $email
     * @return boolean
     */
    public function addUserEmail($email, $callbackUrl)
    {
        $needleCheck = array(
            'email' => true,
        );

        if (!$this->dataHandler->isValidParams(array('email' => $email), $needleCheck)) {
            $response = $this->dataHandler->getLastDataHandlerError();
            return $response;
        }

        if (!$this->checkUserAuth()) {
            $response = $this->getLastError();
            $response['parameters'] = array('callback_url' => $callbackUrl);
            return $response;
        }

        $this->db->update();
        $this->db->tables('`users`');
        $this->db->fields(
            array(
                'users_email' => $email
            )
        );
        $this->db->where('users_id = "' . $_SESSION['auth_users']['users_id'] . '"');
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Update fails to execute'
            );
        }

        $_SESSION['auth_users']['users_email'] = $email;

        return array(
            'type' => 'success',
            'code' => 'add_user_email_true',
            'namespace' => 'add_user_email',
            'parameters' =>
                array(
                    'callback_url' => $callbackUrl
                ),
            'text' => 'User email was added'
        );
    }

    /**
     * Восстановить пароль
     * @param array
     * @return array
     */
    public function forgotPasswordUser($parameters)
    {
        $needleCheck = array(
            'email' => true,
        );

        if (!$this->dataHandler->isValidParams($parameters, $needleCheck)) {
            $response = $this->dataHandler->getLastDataHandlerError();
            return $response;
        }

        // Есть ли у нас в базе пользователь с такой почтой?
        if (!$this->isUserExist($parameters['email'])) {
            return array(
                'type' => 'error',
                'code' => 'user_not_found',
                'namespace' => 'forgot_password_user',
                'text' => 'User with this email was not found'
            );
        }

        $new_password = Functions::generate_password(10);
        $parameters['password'] = $this->encryptionPassword($new_password);

        $params = $this->dataHandler->bindParamsWithType($parameters, 'users');

        if (!$this->updateUser($parameters['email'], $params)) {
            $response = $this->getLastError();
            return $response;
        }

        if (!$this->sendEmailWithNewPassword($parameters['email'], $new_password)) {
            $response = $this->getLastError();
            return $response;
        }

        return array(
            'type' => 'success',
            'code' => 'forgot_password_user_true',
            'namespace' => 'forgot_password_user',
            'text' => 'Email with new password was sent'
        );
    }

    /**
     * Подвердить почту
     * @param $verification
     * @param $email
     * @return boolean
     */
    public function confirmEmailUser($verification, $email)
    {
        $this->db->select();
        $this->db->tables('`registration_confirm`');
        $this->db->fields();
        $this->db->where('registration_confirm_users_email = "' . $email . '" and registration_confirm_verification = "' . $verification . '"');
        $checkVerification = $this->db->execute(true);

        if (!$checkVerification) {
            return false;
        }

        $this->db->delete();
        $this->db->tables('`registration_confirm`');
        $this->db->fields();
        $this->db->where('registration_confirm_users_email = "' . $email . '" and registration_confirm_verification = "' . $verification . '"');
        $this->db->execute(true);

        $params = array('users_email_confirm' => 1);
        $result = $this->updateUser($email, $params);

        if (!$result) {
            return false;
        }

        $this->destroyUserSession();

        return true;
    }

    /**
     * Выход пользователя
     */
    public function logoutUser()
    {
        $this->destroyUserSession();
        return array(
            'type' => 'success',
            'code' => 'logout_user_true',
            'namespace' => 'logout_user',
            'text' => 'User logouted'
        );
    }

    /**
     * Список зарегистрированных пользователей
     * @param string $order_name
     * @param string $order_mode
     * @param int | null $active
     * @param int | null $limit
     * @param int | null $offset
     * @return array
     */
    public function getUsersList($order_name = 'users_registration_date', $order_mode = 'desc', $active = null, $limit = null, $offset = null)
    {
        $this->db->select();
        $this->db->tables('`users`');
        $this->db->fields();

        if (isset($active)) {
            $this->db->where('users_active = "' . $active . '"');
        }

        if (isset($order_name) && isset($order_mode)) {
            $this->db->order($order_name, $order_mode);
        }

        if (isset($limit) && isset($offset)) {
            $this->db->limit($offset, $limit);

            $response['limit'] = $limit;
            $response['offset'] = $offset;
        }

        $response['content'] = $this->db->execute(true);
        $response['count'] = count($response['content']);

        return $response;
    }

    /**
     * Получить одного пользователя
     * @param $id
     * @param int | null $active
     * @return array
     */
    public function getUsersItem($id, $active = null)
    {
        $this->db->select();
        $this->db->tables('`users`');
        $this->db->fields();

        if (isset($active)) {
            $this->db->where('users_active = "' . $active . '" and users_id = "' . $id . '"');
        } else {
            $this->db->where('users_id = "' . $id . '"');
        }

        $response = $this->db->execute(true);

        if (!$response) {
            return false;
        }

        return $response[0];
    }

    /**
     * Список собраных почт пользователей
     * @return array
     */
    public function getUsersEmailsList()
    {
        $this->db->select();
        $this->db->tables('`users`');
        $this->db->fields(
            array(
                'users_email as email'
            )
        );
        $this->db->where('users_email <> "" and users_email IS NOT NULL');
        $users_emails = $this->db->execute(true);

        $this->db->select();
        $this->db->tables('`subscribe`');
        $this->db->fields(
            array(
                'subscribe_email as email'
            )
        );
        $subscribe_emails = $this->db->execute(true);

        $result = array_merge($users_emails, $subscribe_emails);

        $all_emails = array();
        foreach ($result as $email) {
            $all_emails[] = $email['email'];
        }

        $response['content'] = array_unique($all_emails, SORT_STRING);
        $response['count'] = count($response['content']);

        return $response;
    }

    /**
     * Список собранных email - файл для скачки
     * @param string $filetype
     * @param string $filename
     * @return string $filename
     */
    public function getUsersEmailsListFile($filetype, $filename = 'themes/m/docs/emails')
    {
        $filename = $filename . '.' . $filetype;

        if (!is_writable($filename)) {
            return false;
        }

        $emails = $this->getUsersEmailsList();

        $fp = fopen($filename, 'w');
        flock($fp, LOCK_EX);

        if ($filetype === 'csv') {
            foreach ($emails['content'] as $fields) {
                fputcsv($fp, (array)$fields);
            }
        } elseif ($filetype === 'txt') {
            $emails_str = implode(", ", $emails['content']);
            $emails_str = trim($emails_str);
            fwrite($fp, $emails_str);
        } else {
            return false;
        }

        flock($fp, LOCK_UN);
        fclose($fp);

        return $filename;
    }

    /**
     * Активировать/деактивировать пользователя (также активир./деактивиров. работа из конкурса)
     * @param int $id
     * @param string $active
     * @return array
     */
    public function changeActiveUserAdmin($id, $active)
    {
        $needleCheck = array(
            'id' => true,
            'active' => true
        );

        if (!$this->dataHandler->isValidParams(array('id' => $id, 'active' => $active), $needleCheck)) {
            $response = $this->dataHandler->getLastDataHandlerError();
            return $response;
        }

        if ($active == 'on') {
            $active = 1;
        } elseif ($active == 'off') {
            $active = 0;
        }

        $this->db->update();
        $this->db->tables('`competition_member`');
        $this->db->fields(array('competition_member_active' => $active));
        $this->db->where('competition_member_item_id = "' . $id . '"');
        $this->db->execute(true);

        $this->db->update();
        $this->db->tables('`users`');
        $this->db->fields(array('users_active' => $active));
        $this->db->where('users_id = "' . $id . '"');
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Update fails to execute'
            );
        }

        return array(
            'type' => 'success',
            'parameters' =>
                array(
                    'id' => $id,
                    'active' => $active
                ),
            'code' => 'change_active_true',
            'text' => 'User status was changed'
        );
    }

    /**
     * Удалить пользователя (также удаляется работа из конкурса, поставленные им баллы и оставленные им комментарии)
     * @param $id
     * @return array
     */
    public function deleteUserAdmin($id)
    {
        $needleCheck = array(
            'id' => true
        );

        if (!$this->dataHandler->isValidParams(array('id' => $id), $needleCheck)) {
            $response = $this->dataHandler->getLastDataHandlerError();
            return $response;
        }

        $this->db->delete();
        $this->db->tables('`users`');
        $this->db->where('users_id = "' . $id . '"');
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Delete fails to execute'
            );
        }

        return array(
            'type' => 'success',
            'parameters' =>
                array(
                    'id' => $id
                ),
            'code' => 'delete_true',
            'text' => 'User was deleted'
        );
    }

    /**
     * Добавить пользователя
     * @param array
     * @return array
     */
    public function addUserAdmin($parameters)
    {
        $needleCheck = array(
            'name' => true,
            'email' => true,
            'password' => true,
            'repassword' => true,
            'avatar' => true
        );

        if (!$this->dataHandler->isValidParams($parameters, $needleCheck)) {
            $response = $this->dataHandler->getLastDataHandlerError();
            return $response;
        }

        if ($this->isUserExist($parameters['email'])) {
            return array(
                'type' => 'error',
                'code' => 'already_exist',
                'namespace' => 'add_user',
                'text' => 'User with this email has already exist'
            );
        }

        $parameters['password'] = $this->encryptionPassword($parameters['password']);
        $parameters['active'] = 1;
        $parameters['email_confirm'] = 1;
        $params = $this->dataHandler->bindParamsWithType($parameters, 'users');

        if (!$this->addUser($params)) {
            $response = $this->getLastError();
            return $response;
        }

        return array(
            'type' => 'success',
            'code' => 'add_user_true',
            'namespace' => 'add_user',
            'text' => 'User was added'
        );
    }

    /**
     * Зашерить проект
     * @return array
     */
    public function shareProjectByUser()
    {
        if (!$this->isShareAvailable()) {
            return array(
                'type' => 'error',
                'code' => 'need_time_for_share',
                'namespace' => 'share_project',
                'text' => 'Need time for sharing project'
            );
        }

        $this->db->insert();
        $this->db->tables('`shares`');
        $this->db->fields(array(
            'shares_users_id' => $_SESSION['auth_users']['users_id'],
            'shares_users_social_network' => $_SESSION['auth_users']['users_social_network']
        ));
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Insert fails to execute'
            );
        }

        $this->db->update();
        $this->db->tables('`competition_member`');
        $this->db->fields(array(
            'competition_member_rating_for_share' => 'competition_member_rating_for_share + ' . happy::$config['competition_member_rating_for_share'],
            'competition_member_rating_sum' => 'competition_member_rating_sum + ' . happy::$config['competition_member_rating_for_share']
        ), $quotes = false);
        $this->db->where('competition_member_users_id = "' . $_SESSION['auth_users']['users_id'] . '"');
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Update fails to execute'
            );
        }

        return array(
            'type' => 'success',
            'code' => 'share_project_true',
            'namespace' => 'share_project',
            'parameters' =>
                array(
                    'rating' => happy::$config['competition_member_rating_for_share']
                ),
            'text' => 'Share was added'
        );
    }

    /**
     * Вступить в группу
     * @return array
     */
    public function joinGroupByUser($member)
    {
        if ($member == 0) {
            return array(
                'type' => 'error',
                'code' => 'not_invited',
                'namespace' => 'join_group',
                'text' => 'User not invited'
            );
        }


        if (!$this->isJoinGroupAvailable()) {
            return array(
                'type' => 'error',
                'code' => 'already_invited',
                'namespace' => 'join_group',
                'text' => 'User has already invited'
            );
        }

        $this->db->insert();
        $this->db->tables('`social_group`');
        $this->db->fields(array(
            'social_group_users_id' => $_SESSION['auth_users']['users_id'],
            'social_group_users_social_network' => $_SESSION['auth_users']['users_social_network']
        ));
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Insert fails to execute'
            );
        }

        $this->db->update();
        $this->db->tables('`competition_member`');
        $this->db->fields(array(
            'competition_member_rating_for_join_group' => 'competition_member_rating_for_join_group + ' . happy::$config['competition_member_rating_for_join_group'],
            'competition_member_rating_sum' => 'competition_member_rating_sum + ' . happy::$config['competition_member_rating_for_join_group']
        ), $quotes = false);
        $this->db->where('competition_member_users_id = "' . $_SESSION['auth_users']['users_id'] . '"');
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Update fails to execute'
            );
        }

        return array(
            'type' => 'success',
            'code' => 'join_group_true',
            'namespace' => 'join_group',
            'parameters' =>
                array(
                    'rating' => happy::$config['competition_member_rating_for_join_group']
                ),
            'text' => 'User was added to group'
        );
    }

    /**
     * Пригласить друга на проект
     * @param $to
     * @return array
     */
    public function inviteUserFriend($to)
    {
        if (!$this->isUserFriend($to)) {
            return false;
        }

        if (!$this->isInviteFriendAvailable($to)) {
            return array(
                'type' => 'error',
                'code' => 'need_time_for_invite',
                'parameters' =>
                    array(
                        'friend_name' => $_SESSION['friends'][$to]['friends_name']
                    ),
                'namespace' => 'invite_friend',
                'text' => 'Need time for invite friend'
            );
        }

        if ($_SESSION['friends'][$to]['friends_network'] == 'vk') {
            $link = 'https://vk.com/id' . $to;
        } elseif ($_SESSION['friends'][$to]['friends_network'] == 'ok') {
            $link = 'http://odnoklassniki.ru/profile/' . $to;
        }

        $this->db->insert();
        $this->db->tables('`invite`');
        $this->db->fields(array(
            'invite_users_id' => $_SESSION['auth_users']['users_id'],
            'invite_friend_social_uid' => $to,
            'invite_friend_social_profile' => $link,
            'invite_friend_social_network' => $_SESSION['friends'][$to]['friends_network'],
            'invite_friend_avatar' => $_SESSION['friends'][$to]['friends_photo'],
            'invite_friend_name' => $_SESSION['friends'][$to]['friends_name']
        ));
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Insert fails to execute'
            );
        }

        $this->db->update();
        $this->db->tables('`competition_member`');
        $this->db->fields(array(
            'competition_member_rating_for_invite' => 'competition_member_rating_for_invite + ' . happy::$config['competition_member_rating_for_invite'],
            'competition_member_rating_sum' => 'competition_member_rating_sum + ' . happy::$config['competition_member_rating_for_invite']
        ), $quotes = false);
        $this->db->where('competition_member_users_id = "' . $_SESSION['auth_users']['users_id'] . '"');
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Update fails to execute'
            );
        }

        return array(
            'type' => 'success',
            'code' => 'invite_friend_true',
            'namespace' => 'invite_friend',
            'parameters' =>
                array(
                    'rating' => happy::$config['competition_member_rating_for_invite'],
                    'friend_name' => $_SESSION['friends'][$to]['friends_name']
                ),
            'text' => 'Friend was added'
        );
    }

    /**
     * Список друзей ВКонтакте
     * @return array
     */
    public function getUserFriendsVK()
    {
        if (isset($_SESSION['friends'])) {
            return array(
                'type' => 'success',
                'code' => 'get_users_friends_true',
                'namespace' => 'get_users_friends',
                'parameters' =>
                    array(
                        'friends' => $_SESSION['friends'],
                        'network' => 'vk'
                    ),
                'text' => 'Get users friends true'
            );
        }

        $vk_config = Array(
            'app_id' => happy::$config['app_id'],
            'api_secret' => happy::$config['api_secret']
        );

        $vk = new VK($vk_config['app_id'], $vk_config['api_secret']);

        $resp = $vk->api('friends.get',
            array(
                'uid' => $_SESSION['auth_users']['users_social_uid'],
                'fields' => 'uid, first_name, last_name, nickname, photo_big',
                'lang' => 'ru'
            )
        );

        if (!isset($resp['response']) || count($resp['response']) == 0) {
            return array(
                'type' => 'error',
                'code' => 'get_users_friends_empty',
                'namespace' => 'get_users_friends',
                'text' => 'Get users friends empty'
            );
        }

        foreach ($resp['response'] as $data) {
            if (isset($data['first_name']) && isset($data['last_name']) && isset($data['uid'])) {

                if (isset($data['deactivated'])) {
                    continue;
                }

                $data['name'] = $data['first_name'] . ' ' . $data['last_name'];

                $_SESSION['friends'][$data['uid']]['friends_uid'] = $data['uid'];
                $_SESSION['friends'][$data['uid']]['friends_name'] = $data['name'];
                $_SESSION['friends'][$data['uid']]['friends_photo'] = $data['photo_big'];
                $_SESSION['friends'][$data['uid']]['friends_network'] = 'vk';
            }
        }

        return array(
            'type' => 'success',
            'code' => 'get_users_friends_true',
            'namespace' => 'get_users_friends',
            'parameters' =>
                array(
                    'friends' => $_SESSION['friends'],
                    'network' => 'vk'
                ),
            'text' => 'Get users friends true'
        );
    }

    /**
     * Список друзей Одноклассники
     * @return array
     */
    public function getUserFriendsOK()
    {
        if (isset($_SESSION['friends'])) {
            return array(
                'type' => 'success',
                'code' => 'get_users_friends_true',
                'namespace' => 'get_users_friends',
                'parameters' =>
                    array(
                        'friends' => $_SESSION['friends'],
                        'network' => 'ok'
                    ),
                'text' => 'Get users friends true'
            );
        }

        if (isset($_SESSION['ok']['access_token'])) {

            $sign = md5("application_key=" . happy::$config['public_key'] . "format=jsonmethod=friends.get" . md5($_SESSION['ok']['access_token'] . happy::$config['client_secret']));

            $params = array(
                'method' => 'friends.get',
                'access_token' => $_SESSION['ok']['access_token'],
                'application_key' => happy::$config['public_key'],
                'format' => 'json',
                'sig' => $sign
            );

            $userFriends = json_decode(file_get_contents('http://api.odnoklassniki.ru/fb.do' . '?' . urldecode(http_build_query($params))), true);

            if (isset($userFriends) && isset($userFriends['error_code']) && $userFriends['error_code'] == 102) {
                return $this->logoutUser();
            }

            if ($userFriends == null || !isset($userFriends)) {
                return array(
                    'type' => 'error',
                    'code' => 'get_users_friends_empty',
                    'namespace' => 'get_users_friends',
                    'text' => 'Get users friends empty'
                );
            }

            $i = 0;
            $limit = 100;
            $all_friends_info = array();
            $all_count_friends = 0;//общее количество друзей
            $limit_count_friends = 0; //лимитрованное количество друзей не больше 100

            foreach ($userFriends as $item) {
                if ($limit_count_friends >= $limit) {
                    $i++;
                    $limit_count_friends = 0;
                }

                $str[$i][] = $item;

                $limit_count_friends++;
                $all_count_friends++;
            }

            $loops = ceil($all_count_friends / $limit);

            for ($k = 0; $k < $loops; $k++) {
                $uids = implode(",", $str[$k]);
                $sign = md5("application_key=" . happy::$config['public_key'] . "fields=uid,name,pic_3,url_profileformat=jsonmethod=users.getInfouids=" . $uids . md5($_SESSION['ok']['access_token'] . happy::$config['client_secret']));

                $params = array(
                    'method' => 'users.getInfo',
                    'access_token' => $_SESSION['ok']['access_token'],
                    'application_key' => happy::$config['public_key'],
                    'format' => 'json',
                    'sig' => $sign,
                    'uids' => $uids,
                    'fields' => 'uid,name,pic_3,url_profile'
                );

                $userFriendsInfo = json_decode(file_get_contents('http://api.odnoklassniki.ru/fb.do' . '?' . urldecode(http_build_query($params))), true);

                $all_friends_info[] = $userFriendsInfo;
            }

            if ($all_friends_info == null || !isset($all_friends_info)) {
                return array(
                    'type' => 'error',
                    'code' => 'get_users_friends_empty',
                    'namespace' => 'get_users_friends',
                    'text' => 'Get users friends empty'
                );
            }

            foreach ($all_friends_info as $item_info) {
                foreach ($item_info as $data) {
                    if (!isset($data['name']) || !isset($data['uid']) || !isset($data['pic_3'])) {
                        continue;
                    }

                    $_SESSION['friends'][$data['uid']]['friends_uid'] = $data['uid'];
                    $_SESSION['friends'][$data['uid']]['friends_name'] = $data['name'];
                    $_SESSION['friends'][$data['uid']]['friends_photo'] = $data['pic_3'];
                    $_SESSION['friends'][$data['uid']]['friends_network'] = 'ok';
                    $_SESSION['friends'][$data['uid']]['ok_friend_share_link'] = $this->createShareLinkOKProjectByUser('friend/shared/'. $data['uid'], $data['name']);
                }
            }

            return array(
                'type' => 'success',
                'code' => 'get_users_friends_true',
                'namespace' => 'get_users_friends',
                'parameters' =>
                    array(
                        'friends' => $_SESSION['friends'],
                        'network' => 'ok'
                    ),
                'text' => 'Get users friends true'
            );
        }

        return $this->logoutUser();
    }

    /**
     * Пригласить друга в Одноклассниках
     * @param $to
     * @return array
     */
    /*
     * Удалить
     * public function inviteUserFriendOK($to)
    {
        if (!$this->isUserFriend($to)) {
            return false;
        }

        if (!$this->isInviteFriendAvailable($to)) {
            return array(
                'type' => 'error',
                'code' => 'need_time_for_invite',
                'namespace' => 'invite_friend',
                'text' => 'Need time for invite friend'
            );
        }

        $linkShare = happy::$p['root'] . '/test';
        $commentShare = $_SESSION['friends'][$to]['friends_name'] . ' прими участие в конкурсе';

        if (isset($_SESSION['ok']['access_token'])) {
            $sign = md5("application_key=" . happy::$config['public_key'] . "comment=" . $commentShare . "format=jsonlinkUrl=" . $linkShare . "method=share.addLink" . md5($_SESSION['ok']['access_token'] . happy::$config['client_secret']));

            $params = array(
                'application_key' => happy::$config['public_key'],
                'method' => 'share.addLink',
                'format' => 'json',
                'linkUrl' => $linkShare,
                'comment' => $commentShare,
                'access_token' => $_SESSION['ok']['access_token'],
                'sig' => $sign
            );

            $response = json_decode(file_get_contents('http://api.odnoklassniki.ru/fb.do' . '?' . http_build_query($params)), true);

            if (isset($response) && isset($response['error_code']) && $response['error_code'] == 102) {
                return $this->logoutUser();
            }

            if (isset($response) && $response['object_type'] == 'USER_STATUS') {
                return $this->inviteUserFriend($to);
            }

            return false;
        }

        return $this->logoutUser();
    }*/

    /**
     * Создать ссылку для шера в ОК
     * @return array
     */
    public function createShareLinkOKProjectByUser($callbackUrl, $text = null)
    {
        $at = new stdClass();
        $at->media[0]->type = 'text';

        if($text) {
            $at->media[0]->text = 'Привет, ' . $text . '! ' . happy::$config['ok_share_url_text'];
        } else {
            $at->media[0]->text = happy::$config['ok_share_url_text'];
        }

        $at->media[1]->type = 'app';
        $at->media[1]->text = happy::$config['ok_share_message'];
        $at->media[1]->images[0]->url = happy::$config['ok_share_photo'];
        $at->media[1]->images[0]->title = happy::$config['ok_share_photo_title'];
        $at->media[1]->images[0]->mark = happy::$config['ok_share_photo_title'];
        $at->media[1]->actions[0]->text = happy::$config['ok_share_url_text'];
        $at->media[1]->actions[0]->mark = happy::$config['ok_share_url_text'];
        $at->media[2]->type = 'link';
        $at->media[2]->url = happy::$p['root'];

        $attachment_json = json_encode($at);

        $url = 'http://connect.ok.ru/dk?st.cmd=WidgetMediatopicPost';
        $url .= '&st.app=' . happy::$config['client_id'];
        $url .= '&st.attachment=' . $attachment_json;
        //$url .= '&st.signature=' . md5("st.attachment=" . $attachment_json . happy::$config['client_secret']);
        $url .= '&st.signature=' . md5("st.attachment=" . $attachment_json . "st.return=" . happy::$p['root'] . '/' . $callbackUrl . happy::$config['client_secret']);
        $url .= '&st.return=' . happy::$p['root'] . '/' . $callbackUrl;
        $url .= '&st.popup=on';
        return $url;
    }
}