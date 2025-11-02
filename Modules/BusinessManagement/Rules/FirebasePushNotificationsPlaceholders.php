<?php

namespace Modules\BusinessManagement\Rules;
use Illuminate\Contracts\Validation\Rule;


class FirebasePushNotificationsPlaceholders implements Rule
{
    protected array $requiredPlaceholders;
    protected $attribute;

    public function __construct(array $requiredPlaceholders)
    {
        $this->requiredPlaceholders = $requiredPlaceholders;
    }

    public function passes($attribute, $value)
    {
        $this->attribute = $attribute;
        foreach ($this->requiredPlaceholders as $placeholder) {
            if (strpos($value, "{{$placeholder}}") === false) {
                return false;
            }
        }
        return true;
    }

    public function message()
    {
        $notificationKey = explode('.', $this->attribute)[1];
        return 'The value of ' .translate($notificationKey) .' must contain the placeholders: ' . implode(', ', array_map(fn($p): string => "{{$p}}", $this->requiredPlaceholders)) . '.';
    }
}
