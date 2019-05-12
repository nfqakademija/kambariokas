<?php


namespace App\Form;

use App\Form\DataTransformer\UserToUsernameTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class UsernameFormType extends AbstractType
{
    protected $usernameTransformer;

    public function __construct(UserToUsernameTransformer $toUsernameTransformer)
    {
        $this->usernameTransformer = $toUsernameTransformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer($this->usernameTransformer);
    }

    public function getParent()
    {
        return TextType::class;
    }

    public function getBlockPrefix()
    {
        return 'app_username_type';
    }
}