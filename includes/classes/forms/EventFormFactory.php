<?php

namespace Mj\Member\Classes\Forms;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validation;
use function array_key_exists;

if (!defined('ABSPATH')) {
    exit;
}

final class EventFormFactory
{
    private FormFactoryInterface $factory;

    public function __construct(?FormFactoryInterface $factory = null)
    {
        if ($factory !== null) {
            $this->factory = $factory;
            return;
        }

        $builder = Forms::createFormFactoryBuilder();

        if (class_exists(HttpFoundationExtension::class)) {
            $builder->addExtension(new HttpFoundationExtension());
        }

        if (class_exists(Validation::class) && class_exists(ValidatorExtension::class)) {
            $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
            $builder->addExtension(new ValidatorExtension($validator));
        }

        $this->factory = $builder->getFormFactory();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     */
    public function create(array $data = array(), array $options = array()): FormInterface
    {
        $resolved = $this->resolveOptions($options);

        $builder = $this->factory->createBuilder(FormType::class, $data, array(
            'allow_extra_fields' => true,
        ));

        $builder->add('event_title', TextType::class, array(
            'required' => true,
            'label' => __('Titre', 'mj-member'),
            'attr' => array('class' => 'regular-text'),
        ));

        $builder->add('event_status', ChoiceType::class, array(
            'choices' => $this->flipChoices($resolved['status_choices']),
            'label' => __('Statut', 'mj-member'),
            'placeholder' => false,
        ));

        $builder->add('event_type', ChoiceType::class, array(
            'choices' => $this->flipChoices($resolved['type_choices']),
            'label' => __('Type', 'mj-member'),
            'placeholder' => false,
            'choice_attr' => $this->buildChoiceAttrCallback($resolved['type_choice_attributes']),
        ));

        $builder->add('event_accent_color', TextType::class, array(
            'required' => false,
            'label' => __('Couleur pastel', 'mj-member'),
            'attr' => array('class' => 'regular-text', 'data-default-color' => $resolved['accent_default_color']),
        ));

        $builder->add('event_article_cat', ChoiceType::class, array(
            'required' => false,
            'label' => __('Catégorie d\'articles', 'mj-member'),
            'choices' => $this->flipChoices($resolved['article_categories']),
            'placeholder' => __('Aucune catégorie', 'mj-member'),
        ));

        $builder->add('event_article_id', ChoiceType::class, array(
            'required' => false,
            'label' => __('Article lié', 'mj-member'),
            'choices' => $this->flipChoices($resolved['articles']),
            'placeholder' => __('Aucun article', 'mj-member'),
            'choice_attr' => $this->buildChoiceAttrCallback($resolved['article_choice_attributes']),
        ));

        $builder->add('event_cover_id', HiddenType::class);

        $builder->add('event_location_id', ChoiceType::class, array(
            'required' => false,
            'label' => __('Lieu', 'mj-member'),
            'choices' => $this->flipChoices($resolved['locations']),
            'placeholder' => __('Aucun lieu défini', 'mj-member'),
            'choice_attr' => $this->buildChoiceAttrCallback($resolved['location_choice_attributes']),
        ));

        $builder->add('event_animateur_ids', ChoiceType::class, array(
            'required' => false,
            'multiple' => true,
            'label' => __('Animateurs référents', 'mj-member'),
            'choices' => $this->flipChoices($resolved['animateurs']),
            'choice_attr' => $this->buildChoiceAttrCallback($resolved['animateur_choice_attributes']),
        ));

        $builder->add('event_volunteer_ids', ChoiceType::class, array(
            'required' => false,
            'multiple' => true,
            'label' => __('Bénévoles référents', 'mj-member'),
            'choices' => $this->flipChoices($resolved['volunteers']),
            'choice_attr' => $this->buildChoiceAttrCallback($resolved['volunteer_choice_attributes']),
        ));

        $builder->add('event_allow_guardian_registration', CheckboxType::class, array(
            'required' => false,
            'label' => __('Autoriser les tuteurs', 'mj-member'),
        ));

        $builder->add('event_free_participation', CheckboxType::class, array(
            'required' => false,
            'label' => __('Participation libre', 'mj-member'),
        ));

        $builder->add('event_requires_validation', CheckboxType::class, array(
            'required' => false,
            'label' => __('Validation des inscriptions', 'mj-member'),
        ));

        $builder->add('event_capacity_total', IntegerType::class, array(
            'required' => false,
            'label' => __('Places max', 'mj-member'),
        ));

        $builder->add('event_capacity_waitlist', IntegerType::class, array(
            'required' => false,
            'label' => __('Liste d\'attente', 'mj-member'),
        ));

        $builder->add('event_capacity_notify_threshold', IntegerType::class, array(
            'required' => false,
            'label' => __('Seuil d\'alerte', 'mj-member'),
        ));

        $builder->add('event_age_min', IntegerType::class, array(
            'required' => false,
            'label' => __('Âge minimum', 'mj-member'),
        ));

        $builder->add('event_age_max', IntegerType::class, array(
            'required' => false,
            'label' => __('Âge maximum', 'mj-member'),
        ));

        $builder->add('event_schedule_mode', ChoiceType::class, array(
            'choices' => $this->flipChoices($resolved['schedule_modes']),
            'label' => __('Planification', 'mj-member'),
            'expanded' => true,
        ));

        $builder->add('event_date_start', HiddenType::class);
        $builder->add('event_date_end', HiddenType::class);

        $builder->add('event_fixed_date', TextType::class, array(
            'required' => false,
            'label' => __('Jour', 'mj-member'),
            'attr' => array('type' => 'date'),
        ));
        $builder->add('event_fixed_start_time', TextType::class, array(
            'required' => false,
            'label' => __('Début', 'mj-member'),
            'attr' => array('type' => 'time'),
        ));
        $builder->add('event_fixed_end_time', TextType::class, array(
            'required' => false,
            'label' => __('Fin', 'mj-member'),
            'attr' => array('type' => 'time'),
        ));

        $builder->add('event_range_start', TextType::class, array(
            'required' => false,
            'label' => __('Début de plage', 'mj-member'),
            'attr' => array('type' => 'datetime-local'),
        ));
        $builder->add('event_range_end', TextType::class, array(
            'required' => false,
            'label' => __('Fin de plage', 'mj-member'),
            'attr' => array('type' => 'datetime-local'),
        ));

        $builder->add('event_recurring_start_date', TextType::class, array(
            'required' => false,
            'label' => __('Jour', 'mj-member'),
            'attr' => array('type' => 'date'),
        ));

        $builder->add('event_recurring_frequency', ChoiceType::class, array(
            'choices' => $this->flipChoices($resolved['recurring_frequencies']),
            'label' => __('Fréquence', 'mj-member'),
            'placeholder' => false,
        ));

        $builder->add('event_recurring_interval', IntegerType::class, array(
            'required' => false,
            'label' => __('Intervalle', 'mj-member'),
            'empty_data' => '1',
        ));

        $builder->add('event_recurring_weekdays', ChoiceType::class, array(
            'required' => false,
            'multiple' => true,
            'expanded' => true,
            'label' => __('Jours concernés', 'mj-member'),
            'choices' => $this->flipChoices($resolved['schedule_weekdays']),
        ));

        $builder->add('event_recurring_month_ordinal', ChoiceType::class, array(
            'required' => false,
            'label' => __('Occurrence mensuelle', 'mj-member'),
            'choices' => $this->flipChoices($resolved['schedule_month_ordinals']),
            'placeholder' => false,
        ));

        $builder->add('event_recurring_month_weekday', ChoiceType::class, array(
            'required' => false,
            'label' => __('Jour cible', 'mj-member'),
            'choices' => $this->flipChoices($resolved['schedule_weekdays']),
            'placeholder' => false,
        ));

        $builder->add('event_recurring_until', TextType::class, array(
            'required' => false,
            'label' => __('Fin de récurrence', 'mj-member'),
            'attr' => array('type' => 'date'),
        ));

        $builder->add('event_series_items', HiddenType::class);

        $builder->add('event_occurrence_selection_mode', ChoiceType::class, array(
            'choices' => $this->flipChoices($resolved['occurrence_modes']),
            'label' => __('Gestion des occurrences', 'mj-member'),
            'expanded' => true,
        ));

        $builder->add('event_date_deadline', TextType::class, array(
            'required' => false,
            'label' => __('Date limite d\'inscription', 'mj-member'),
            'attr' => array('type' => 'datetime-local'),
        ));

        $builder->add('event_price', NumberType::class, array(
            'required' => false,
            'label' => __('Tarif', 'mj-member'),
            'scale' => 2,
        ));

        $builder->add('event_description', TextareaType::class, array(
            'required' => false,
            'label' => __('Description détaillée', 'mj-member'),
        ));

        return $builder->getForm();
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(array(
            'status_choices' => array(),
            'type_choices' => array(),
            'type_choice_attributes' => array(),
            'accent_default_color' => '',
            'article_categories' => array(),
            'articles' => array(),
            'article_choice_attributes' => array(),
            'locations' => array(),
            'location_choice_attributes' => array(),
            'animateurs' => array(),
            'animateur_choice_attributes' => array(),
            'volunteers' => array(),
            'volunteer_choice_attributes' => array(),
            'schedule_modes' => array(
                'fixed' => __('Date fixe', 'mj-member'),
                'range' => __('Plage de dates', 'mj-member'),
                'recurring' => __('Récurrence', 'mj-member'),
                'series' => __('Série personnalisée', 'mj-member'),
            ),
            'recurring_frequencies' => array(
                'weekly' => __('Hebdomadaire', 'mj-member'),
                'monthly' => __('Mensuelle', 'mj-member'),
            ),
            'schedule_weekdays' => array(),
            'schedule_month_ordinals' => array(),
            'occurrence_modes' => array(
                'member_choice' => __('Les membres choisissent leurs occurrences', 'mj-member'),
                'all_occurrences' => __('Inscrire automatiquement sur toutes les occurrences', 'mj-member'),
            ),
        ));

        $resolver->setAllowedTypes('status_choices', 'array');
        $resolver->setAllowedTypes('type_choices', 'array');
        $resolver->setAllowedTypes('type_choice_attributes', 'array');
        $resolver->setAllowedTypes('accent_default_color', 'string');
        $resolver->setAllowedTypes('article_categories', 'array');
        $resolver->setAllowedTypes('articles', 'array');
        $resolver->setAllowedTypes('article_choice_attributes', 'array');
        $resolver->setAllowedTypes('locations', 'array');
        $resolver->setAllowedTypes('location_choice_attributes', 'array');
        $resolver->setAllowedTypes('animateurs', 'array');
        $resolver->setAllowedTypes('animateur_choice_attributes', 'array');
        $resolver->setAllowedTypes('volunteers', 'array');
        $resolver->setAllowedTypes('volunteer_choice_attributes', 'array');
        $resolver->setAllowedTypes('schedule_modes', 'array');
        $resolver->setAllowedTypes('recurring_frequencies', 'array');
        $resolver->setAllowedTypes('schedule_weekdays', 'array');
        $resolver->setAllowedTypes('schedule_month_ordinals', 'array');
        $resolver->setAllowedTypes('occurrence_modes', 'array');

        return $resolver->resolve($options);
    }

    /**
     * @param array<string,string|int> $choices
     * @return array<string,string|int>
     */
    private function flipChoices(array $choices): array
    {
        $result = array();
        foreach ($choices as $value => $label) {
            if (!is_scalar($value)) {
                continue;
            }
            $result[(string) $label] = $value;
        }

        return $result;
    }

    /**
     * @param array<string,array<string,string>> $attributes
     * @return callable
     */
    private function buildChoiceAttrCallback(array $attributes): callable
    {
        return static function ($choice, $key, $index) use ($attributes): array {
            if (is_scalar($choice) && array_key_exists((string) $choice, $attributes)) {
                return $attributes[(string) $choice];
            }
            if (is_scalar($key) && array_key_exists((string) $key, $attributes)) {
                return $attributes[(string) $key];
            }

            return array();
        };
    }
}
