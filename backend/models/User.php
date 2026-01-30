<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

class User extends ActiveRecord implements IdentityInterface
{
    public static function tableName()
    {
        return '{{%users}}';
    }

    public function rules()
    {
        return [
            [['name', 'email', 'password_hash', 'role', 'created_at', 'updated_at'], 'required'],
            [['created_at', 'updated_at'], 'integer'],
            [['company_rating'], 'number'],
            [['name'], 'string', 'max' => 120],
            [['email'], 'string', 'max' => 190],
            [['email'], 'email'],
            [['email'], 'unique'],
            [['password_hash'], 'string', 'max' => 255],
            [['company_name'], 'string', 'max' => 190],
            ['role', 'in', 'range' => ['user', 'company']],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'name',
            'email',
            'role',
            'company_name',
            'company_rating',
            'created_at',
            'updated_at',
        ];
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public static function findByEmail(string $email): ?self
    {
        return static::findOne(['email' => $email]);
    }

    // IdentityInterface
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id]);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return null;
    }

    public function validateAuthKey($authKey)
    {
        return false;
    }
}
