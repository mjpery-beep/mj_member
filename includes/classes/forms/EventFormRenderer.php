<?php

namespace Mj\Member\Classes\Forms;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Génère les champs de formulaire réutilisables pour les événements
 */
class EventFormRenderer
{
    /**
     * Labels des jours de la semaine
     */
    private static function getWeekdayLabels(): array
    {
        return [
            'monday' => __('Lundi', 'mj-member'),
            'tuesday' => __('Mardi', 'mj-member'),
            'wednesday' => __('Mercredi', 'mj-member'),
            'thursday' => __('Jeudi', 'mj-member'),
            'friday' => __('Vendredi', 'mj-member'),
            'saturday' => __('Samedi', 'mj-member'),
            'sunday' => __('Dimanche', 'mj-member'),
        ];
    }

    private static function parseRegistrationPayload($source): array
    {
        if (is_array($source)) {
            return $source;
        }

        if (is_string($source) && $source !== '') {
            $decoded = json_decode($source, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Labels des ordinaux mensuels
     */
    private static function getMonthOrdinalLabels(): array
    {
        return [
            'first' => __('Premier', 'mj-member'),
            'second' => __('Deuxième', 'mj-member'),
            'third' => __('Troisième', 'mj-member'),
            'fourth' => __('Quatrième', 'mj-member'),
            'last' => __('Dernier', 'mj-member'),
        ];
    }

    /**
     * Rend un champ de texte
     *
     * @param string $id ID du champ
     * @param string $label Label du champ
     * @param string $value Valeur actuelle
     * @param array<string,mixed> $args Arguments supplémentaires
     * @return void
     */
    public static function renderTextField(string $id, string $label, string $value = '', array $args = []): void
    {
        $required = !empty($args['required']);
        $placeholder = $args['placeholder'] ?? '';
        $description = $args['description'] ?? '';
        $maxlength = $args['maxlength'] ?? '';
        $class = $args['class'] ?? 'regular-text';
        $wrapper_class = $args['wrapper_class'] ?? 'mj-form-field';

        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>">
            <label for="<?php echo esc_attr($date_input_id); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($required): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
            <input
                type="text"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id); ?>"
                value="<?php echo esc_attr($value); ?>"
                class="<?php echo esc_attr($class); ?>"
                <?php if ($required): ?>required<?php endif; ?>
                <?php if ($placeholder): ?>placeholder="<?php echo esc_attr($placeholder); ?>"<?php endif; ?>
                <?php if ($maxlength): ?>maxlength="<?php echo esc_attr($maxlength); ?>"<?php endif; ?>
            />
            <?php if ($description): ?>
                <p class="description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Rend un champ textarea
     *
     * @param string $id ID du champ
     * @param string $label Label du champ
     * @param string $value Valeur actuelle
     * @param array<string,mixed> $args Arguments supplémentaires
     * @return void
     */
    public static function renderTextarea(string $id, string $label, string $value = '', array $args = []): void
    {
        $required = !empty($args['required']);
        $placeholder = $args['placeholder'] ?? '';
        $description = $args['description'] ?? '';
        $rows = $args['rows'] ?? 5;
        $class = $args['class'] ?? 'large-text';
        $wrapper_class = $args['wrapper_class'] ?? 'mj-form-field';

        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>">
            <label for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($required): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
            <textarea
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id); ?>"
                class="<?php echo esc_attr($class); ?>"
                rows="<?php echo esc_attr($rows); ?>"
                <?php if ($required): ?>required<?php endif; ?>
                <?php if ($placeholder): ?>placeholder="<?php echo esc_attr($placeholder); ?>"<?php endif; ?>
            ><?php echo esc_textarea($value); ?></textarea>
            <?php if ($description): ?>
                <p class="description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Rend un champ select
     *
     * @param string $id ID du champ
     * @param string $label Label du champ
     * @param string $value Valeur actuelle
     * @param array<string,string> $options Options du select
     * @param array<string,mixed> $args Arguments supplémentaires
     * @return void
     */
    public static function renderSelect(string $id, string $label, string $value = '', array $options = [], array $args = []): void
    {
        $required = !empty($args['required']);
        $description = $args['description'] ?? '';
        $class = $args['class'] ?? '';
        $wrapper_class = $args['wrapper_class'] ?? 'mj-form-field';
        $empty_option = $args['empty_option'] ?? '';

        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>">
            <label for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($required): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
            <select
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id); ?>"
                class="<?php echo esc_attr($class); ?>"
                <?php if ($required): ?>required<?php endif; ?>
            >
                <?php if ($empty_option): ?>
                    <option value=""><?php echo esc_html($empty_option); ?></option>
                <?php endif; ?>
                <?php foreach ($options as $option_value => $option_label): ?>
                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                        <?php echo esc_html($option_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($description): ?>
                <p class="description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Rend un champ datetime-local
     *
     * @param string $id ID du champ
     * @param string $label Label du champ
     * @param string $value Valeur actuelle (format Y-m-d H:i:s)
     * @param array<string,mixed> $args Arguments supplémentaires
     * @return void
     */
    public static function renderDatetime(string $id, string $label, string $value = '', array $args = []): void
    {
        $required = !empty($args['required']);
        $description = $args['description'] ?? '';
        $class = $args['class'] ?? '';
        $wrapper_class = $args['wrapper_class'] ?? 'mj-form-field';
        $require_time = array_key_exists('require_time', $args) ? (bool) $args['require_time'] : $required;

        $hidden_value = '';
        $date_value = '';
        $time_value = '';

        if (!empty($value) && $value !== '0000-00-00 00:00:00') {
            try {
                $timezone = wp_timezone();

                $datetime = null;

                if ($value instanceof \DateTimeInterface) {
                    $datetime = new \DateTime($value->format('Y-m-d H:i:s'), $timezone);
                }

                if (!$datetime instanceof \DateTimeInterface && is_string($value)) {
                    $normalized = str_replace('T', ' ', $value);
                    $datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $normalized, $timezone);
                    if (!$datetime instanceof \DateTime) {
                        $datetime = \DateTime::createFromFormat('Y-m-d H:i', $normalized, $timezone);
                    }
                }

                if (!$datetime instanceof \DateTimeInterface) {
                    $timestamp = strtotime((string) $value);
                    if ($timestamp) {
                        $datetime = new \DateTime('@' . $timestamp);
                        $datetime->setTimezone($timezone);
                    }
                }

                if ($datetime instanceof \DateTimeInterface) {
                    $date_value = $datetime->format('Y-m-d');
                    $time_value = $datetime->format('H:i');
                    $hidden_value = $time_value !== ''
                        ? $datetime->format('Y-m-d\TH:i')
                        : $date_value;
                }
            } catch (\Exception $e) {
                error_log('EventFormRenderer: Invalid datetime format - ' . $e->getMessage());
            }
        }

        $date_input_id = $id . '_date';
        $time_input_id = $id . '_time';
        $input_class = trim($class . ' mj-form-datetime__input');

        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>">
            <label for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($required): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
            <input
                type="hidden"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id); ?>"
                value="<?php echo esc_attr($hidden_value); ?>"
                data-datetime-hidden="<?php echo esc_attr($id); ?>"
            />
            <div
                class="mj-form-datetime"
                data-datetime-composite
                data-datetime-target="<?php echo esc_attr($id); ?>"
                data-datetime-require-time="<?php echo $require_time ? '1' : '0'; ?>"
            >
                <div class="mj-form-datetime__column">
                    <span class="mj-form-datetime__label" aria-hidden="true"><?php esc_html_e('Date', 'mj-member'); ?></span>
                    <input
                        type="date"
                        id="<?php echo esc_attr($date_input_id); ?>"
                        name="<?php echo esc_attr($id); ?>_date"
                        value="<?php echo esc_attr($date_value); ?>"
                        class="<?php echo esc_attr($input_class); ?>"
                        data-datetime-date
                        aria-label="<?php echo esc_attr(sprintf(__('Date pour %s', 'mj-member'), $label)); ?>"
                        <?php if ($required): ?>required<?php endif; ?>
                    />
                </div>
                <div class="mj-form-datetime__column">
                    <span class="mj-form-datetime__label" aria-hidden="true"><?php esc_html_e('Heure', 'mj-member'); ?></span>
                    <input
                        type="time"
                        id="<?php echo esc_attr($time_input_id); ?>"
                        name="<?php echo esc_attr($id); ?>_time"
                        value="<?php echo esc_attr($time_value); ?>"
                        class="<?php echo esc_attr($input_class); ?>"
                        data-datetime-time
                        aria-label="<?php echo esc_attr(sprintf(__('Heure pour %s', 'mj-member'), $label)); ?>"
                        <?php if ($require_time): ?>required<?php endif; ?>
                    />
                </div>
            </div>
            <?php if ($description): ?>
                <p class="description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Rend un champ number
     *
     * @param string $id ID du champ
     * @param string $label Label du champ
     * @param float|int $value Valeur actuelle
     * @param array<string,mixed> $args Arguments supplémentaires
     * @return void
     */
    public static function renderNumber(string $id, string $label, $value = 0, array $args = []): void
    {
        $required = !empty($args['required']);
        $description = $args['description'] ?? '';
        $min = $args['min'] ?? '';
        $max = $args['max'] ?? '';
        $step = $args['step'] ?? '1';
        $class = $args['class'] ?? '';
        $wrapper_class = $args['wrapper_class'] ?? 'mj-form-field';

        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>">
            <label for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($required): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
            <input
                type="number"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id); ?>"
                value="<?php echo esc_attr($value); ?>"
                class="<?php echo esc_attr($class); ?>"
                <?php if ($required): ?>required<?php endif; ?>
                <?php if ($min !== ''): ?>min="<?php echo esc_attr($min); ?>"<?php endif; ?>
                <?php if ($max !== ''): ?>max="<?php echo esc_attr($max); ?>"<?php endif; ?>
                step="<?php echo esc_attr($step); ?>"
            />
            <?php if ($description): ?>
                <p class="description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Rend un groupe de champs horizontaux (ex: date de début et fin côte à côte)
     *
     * @param callable $callback Fonction qui génère le contenu du groupe
     * @param array<string,mixed> $args Arguments supplémentaires
     * @return void
     */
    public static function renderFieldGroup(callable $callback, array $args = []): void
    {
        $class = $args['class'] ?? 'mj-form-field-group';
        $label = $args['label'] ?? '';

        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <?php if ($label): ?>
                <div class="mj-form-field-group__label"><?php echo esc_html($label); ?></div>
            <?php endif; ?>
            <div class="mj-form-field-group__fields">
                <?php call_user_func($callback); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Rend un champ toggle/checkbox avec étiquette principale
     *
     * @param string $id ID du champ
     * @param string $legend Label principal affiché au-dessus du toggle
     * @param string $label Label associé au checkbox
     * @param bool $checked Valeur de sélection actuelle
     * @param array<string,mixed> $args Arguments supplémentaires
     * @return void
     */
    public static function renderToggleField(string $id, string $legend, string $label, bool $checked = false, array $args = []): void
    {
        $description = $args['description'] ?? '';
        $wrapper_class = $args['wrapper_class'] ?? 'mj-form-field';
        $toggle_class = $args['toggle_class'] ?? 'mj-form-toggle';
        $value = $args['value'] ?? '1';

        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>">
            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($legend); ?></label>
            <div class="<?php echo esc_attr($toggle_class); ?>">
                <label for="<?php echo esc_attr($id); ?>">
                    <input
                        type="checkbox"
                        id="<?php echo esc_attr($id); ?>"
                        name="<?php echo esc_attr($id); ?>"
                        value="<?php echo esc_attr($value); ?>"
                        <?php checked($checked, true); ?>
                    />
                    <?php echo esc_html($label); ?>
                </label>
            </div>
            <?php if ($description): ?>
                <p class="description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Rend les champs de base d'un événement
     *
     * @param array<string,mixed> $event Données de l'événement (ou tableau vide pour création)
     * @param array<string,array<string,string>> $options Options pour les selects (types, statuts, etc.)
     * @return void
     */
    public static function renderBasicFields(array $event, array $options): void
    {
        if (!class_exists('MjEvents')) {
            require_once \Mj\Member\Core\Config::path() . 'includes/classes/crud/MjEvents.php';
        }

        $title = $event['title'] ?? '';
        $description = $event['description'] ?? '';
        $type = $event['type'] ?? '';
        $status = $event['status'] ?? 'draft';
        $start_date = $event['start_date'] ?? '';
        $end_date = $event['end_date'] ?? '';
        $price = $event['price'] ?? 0;
        $capacity_total = $event['capacity_total'] ?? 0;
        $emoji_value = '';
        if (!empty($event['emoji']) && !is_array($event['emoji'])) {
            $emoji_candidate = sanitize_text_field((string) $event['emoji']);
            if ($emoji_candidate !== '') {
                if (function_exists('mb_substr')) {
                    $emoji_candidate = mb_substr($emoji_candidate, 0, 16);
                } else {
                    $emoji_candidate = substr($emoji_candidate, 0, 16);
                }
                $emoji_value = $emoji_candidate;
            }
        }

        $registration_payload = [];
        if (!empty($event['registration_payload'])) {
            $registration_payload = self::parseRegistrationPayload($event['registration_payload']);
        }
        if (isset($event['attendance_show_all_members']) && $event['attendance_show_all_members'] !== null) {
            $registration_payload['attendance_show_all_members'] = $event['attendance_show_all_members'];
        }
        $attendance_show_all_members = !empty($registration_payload['attendance_show_all_members']);

        $event_types = $options['types'] ?? \MjEvents::get_type_labels();
        $event_statuses = $options['statuses'] ?? \MjEvents::get_status_labels();

        self::renderTextField(
            'title',
            __('Titre de l\'événement', 'mj-member'),
            $title,
            [
                'required' => true,
                'placeholder' => __('Ex : Soirée jeux de société', 'mj-member'),
                'class' => 'widefat',
            ]
        );

        $emoji_input_id = 'mj-event-emoji';
        $emoji_hint_id = 'mj-event-emoji-hint';
        $emoji_placeholder = __('Ex : 🎉', 'mj-member');
        $emoji_hint_text = __('Facultatif, affiché avec le titre.', 'mj-member');
        ?>
        <div class="mj-form-field mj-form-field--emoji" data-emoji-field>
            <label for="<?php echo esc_attr($emoji_input_id); ?>"><?php esc_html_e('Emoji', 'mj-member'); ?></label>
            <div class="mj-form-emoji" data-emoji-container>
                <div class="mj-form-emoji__picker" data-emoji-picker-root></div>
                <input
                    type="text"
                    id="<?php echo esc_attr($emoji_input_id); ?>"
                    name="emoji"
                    class="mj-form-emoji__fallback"
                    value="<?php echo esc_attr($emoji_value); ?>"
                    maxlength="16"
                    placeholder="<?php echo esc_attr($emoji_placeholder); ?>"
                    autocomplete="off"
                    data-emoji-input
                    aria-describedby="<?php echo esc_attr($emoji_hint_id); ?>"
                />
            </div>
            <p class="description mj-form-emoji__hint" id="<?php echo esc_attr($emoji_hint_id); ?>"><?php echo esc_html($emoji_hint_text); ?></p>
        </div>
        <?php

        self::renderTextarea(
            'description',
            __('Description', 'mj-member'),
            $description,
            [
                'placeholder' => __('Décrivez l\'événement...', 'mj-member'),
                'rows' => 4,
                'class' => 'widefat',
            ]
        );

        self::renderFieldGroup(function () use ($type, $event_types, $status, $event_statuses) {
            self::renderSelect(
                'type',
                __('Type', 'mj-member'),
                $type,
                $event_types,
                [
                    'required' => true,
                    'wrapper_class' => 'mj-form-field mj-form-field--inline',
                ]
            );

            self::renderSelect(
                'status',
                __('Statut', 'mj-member'),
                $status,
                $event_statuses,
                [
                    'required' => true,
                    'wrapper_class' => 'mj-form-field mj-form-field--inline',
                ]
            );
        }, [
            'class' => 'mj-form-field-group mj-form-field-group--row',
        ]);

        self::renderToggleField(
            'attendance_show_all_members',
            __('Liste de présence', 'mj-member'),
            __('Afficher tous les membres dans la liste de présence', 'mj-member'),
            $attendance_show_all_members,
            [
                'description' => __('Permet de pointer les membres autorisés même sans inscription préalable.', 'mj-member'),
            ]
        );

        ?>
        <div class="mj-form-field-group mj-form-field-group--row" data-schedule-datetime-group>
            <?php
            self::renderDatetime(
                'start_date',
                __('Date et heure de début', 'mj-member'),
                $start_date,
                [
                    'required' => true,
                    'wrapper_class' => 'mj-form-field mj-form-field--inline',
                    'require_time' => true,
                ]
            );

            self::renderDatetime(
                'end_date',
                __('Date et heure de fin', 'mj-member'),
                $end_date,
                [
                    'wrapper_class' => 'mj-form-field mj-form-field--inline',
                    'require_time' => false,
                ]
            );
            ?>
        </div>
        <div class="mj-form-field mj-form-field--full" data-schedule-range-note hidden>
            <p class="description"><?php esc_html_e('Pour une plage de dates, indiquez la date et l\'heure de début et de fin ci-dessus.', 'mj-member'); ?></p>
        </div>
        <?php

        self::renderFieldGroup(function () use ($price, $capacity_total) {
            self::renderNumber(
                'price',
                __('Prix (€)', 'mj-member'),
                $price,
                [
                    'min' => '0',
                    'step' => '0.01',
                    'wrapper_class' => 'mj-form-field mj-form-field--inline',
                ]
            );

            self::renderNumber(
                'capacity_total',
                __('Capacité totale', 'mj-member'),
                $capacity_total,
                [
                    'min' => '0',
                    'step' => '1',
                    'description' => __('0 = illimité', 'mj-member'),
                    'wrapper_class' => 'mj-form-field mj-form-field--inline',
                ]
            );
        }, [
            'class' => 'mj-form-field-group mj-form-field-group--row',
        ]);
    }

    /**
     * Rend les champs de planification (mode + récurrence)
     *
     * @param array<string,mixed> $event Données de l'événement
     * @return void
     */
    public static function renderScheduleFields(array $event): void
    {
        $schedule_mode = $event['schedule_mode'] ?? 'fixed';
        $schedule_payload = [];
        if (!empty($event['schedule_payload'])) {
            if (is_string($event['schedule_payload'])) {
                $decoded = json_decode($event['schedule_payload'], true);
                if (is_array($decoded)) {
                    $schedule_payload = $decoded;
                }
            } elseif (is_array($event['schedule_payload'])) {
                $schedule_payload = $event['schedule_payload'];
            }
        }

        $frequency = $schedule_payload['frequency'] ?? 'weekly';
        $interval = $schedule_payload['interval'] ?? 1;
        $weekdays = $schedule_payload['weekdays'] ?? [];
        $weekday_times = $schedule_payload['weekday_times'] ?? [];
        $ordinal = $schedule_payload['ordinal'] ?? 'first';
        $monthly_weekday = $schedule_payload['weekday'] ?? 'saturday';
        $until_date = $schedule_payload['until'] ?? '';

        // Start/end time par défaut (utilisé si pas de plage par jour)
        $start_time = $schedule_payload['start_time'] ?? '';
        $end_time = $schedule_payload['end_time'] ?? '';

        $weekday_labels = self::getWeekdayLabels();
        $ordinal_labels = self::getMonthOrdinalLabels();
        ?>

        <div class="mj-form-field">
            <label><?php esc_html_e('Mode de planification', 'mj-member'); ?></label>
            <div class="mj-form-radio-group">
                <label class="mj-form-radio">
                    <input type="radio" name="schedule_mode" value="fixed" <?php checked($schedule_mode, 'fixed'); ?> data-schedule-mode />
                    <?php esc_html_e('Date fixe', 'mj-member'); ?>
                </label>
                <label class="mj-form-radio">
                    <input type="radio" name="schedule_mode" value="range" <?php checked($schedule_mode, 'range'); ?> data-schedule-mode />
                    <?php esc_html_e('Plage de dates', 'mj-member'); ?>
                </label>
                <label class="mj-form-radio">
                    <input type="radio" name="schedule_mode" value="recurring" <?php checked($schedule_mode, 'recurring'); ?> data-schedule-mode />
                    <?php esc_html_e('Récurrence', 'mj-member'); ?>
                </label>
            </div>
        </div>

        <!-- Section récurrence (masquée par défaut si mode=fixed) -->
        <div class="mj-form-schedule-recurring" data-schedule-recurring-section <?php if ($schedule_mode !== 'recurring') echo 'hidden'; ?>>
            <div class="mj-form-field">
                <label for="recurring_frequency"><?php esc_html_e('Fréquence', 'mj-member'); ?></label>
                <select id="recurring_frequency" name="recurring_frequency" data-recurring-frequency>
                    <option value="weekly" <?php selected($frequency, 'weekly'); ?>><?php esc_html_e('Hebdomadaire', 'mj-member'); ?></option>
                    <option value="monthly" <?php selected($frequency, 'monthly'); ?>><?php esc_html_e('Mensuelle', 'mj-member'); ?></option>
                </select>
            </div>

            <div class="mj-form-field">
                <label for="recurring_interval"><?php esc_html_e('Intervalle', 'mj-member'); ?></label>
                <div class="mj-form-input-group">
                    <span><?php esc_html_e('Toutes les', 'mj-member'); ?></span>
                    <input type="number" id="recurring_interval" name="recurring_interval" value="<?php echo esc_attr($interval); ?>" min="1" max="52" style="width:60px" />
                    <span data-interval-label><?php esc_html_e('semaine(s)', 'mj-member'); ?></span>
                </div>
            </div>

            <!-- Section hebdomadaire -->
            <div class="mj-form-schedule-weekly" data-schedule-weekly-section <?php if ($frequency !== 'weekly') echo 'hidden'; ?>>
                <div class="mj-form-field">
                    <label><?php esc_html_e('Jours de la semaine', 'mj-member'); ?></label>
                    <div class="mj-form-weekday-selector" data-weekday-selector>
                        <?php foreach ($weekday_labels as $day_key => $day_label) : 
                            $is_checked = in_array($day_key, $weekdays, true);
                            $day_times = $weekday_times[$day_key] ?? [];
                            $day_start = $day_times['start'] ?? $start_time;
                            $day_end = $day_times['end'] ?? $end_time;
                        ?>
                        <div class="mj-form-weekday-item" data-weekday="<?php echo esc_attr($day_key); ?>">
                            <label class="mj-form-weekday-checkbox">
                                <input type="checkbox" 
                                       name="recurring_weekdays[]" 
                                       value="<?php echo esc_attr($day_key); ?>" 
                                       <?php checked($is_checked); ?>
                                       data-weekday-checkbox />
                                <span class="mj-form-weekday-label"><?php echo esc_html($day_label); ?></span>
                            </label>
                            <div class="mj-form-weekday-times" data-weekday-times <?php if (!$is_checked) echo 'hidden'; ?>>
                                <input type="time" 
                                       name="weekday_times[<?php echo esc_attr($day_key); ?>][start]" 
                                       value="<?php echo esc_attr($day_start); ?>"
                                       placeholder="Début"
                                       aria-label="<?php echo esc_attr(sprintf(__('Heure de début pour %s', 'mj-member'), $day_label)); ?>" />
                                <span>→</span>
                                <input type="time" 
                                       name="weekday_times[<?php echo esc_attr($day_key); ?>][end]" 
                                       value="<?php echo esc_attr($day_end); ?>"
                                       placeholder="Fin"
                                       aria-label="<?php echo esc_attr(sprintf(__('Heure de fin pour %s', 'mj-member'), $day_label)); ?>" />
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Section mensuelle -->
            <div class="mj-form-schedule-monthly" data-schedule-monthly-section <?php if ($frequency !== 'monthly') echo 'hidden'; ?>>
                <div class="mj-form-field-group mj-form-field-group--row">
                    <div class="mj-form-field mj-form-field--inline">
                        <label for="recurring_ordinal"><?php esc_html_e('Occurrence', 'mj-member'); ?></label>
                        <select id="recurring_ordinal" name="recurring_ordinal">
                            <?php foreach ($ordinal_labels as $ord_key => $ord_label) : ?>
                                <option value="<?php echo esc_attr($ord_key); ?>" <?php selected($ordinal, $ord_key); ?>><?php echo esc_html($ord_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mj-form-field mj-form-field--inline">
                        <label for="recurring_monthly_weekday"><?php esc_html_e('Jour', 'mj-member'); ?></label>
                        <select id="recurring_monthly_weekday" name="recurring_monthly_weekday">
                            <?php foreach ($weekday_labels as $day_key => $day_label) : ?>
                                <option value="<?php echo esc_attr($day_key); ?>" <?php selected($monthly_weekday, $day_key); ?>><?php echo esc_html($day_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mj-form-field-group mj-form-field-group--row">
                    <div class="mj-form-field mj-form-field--inline">
                        <label for="recurring_monthly_start_time"><?php esc_html_e('Heure de début', 'mj-member'); ?></label>
                        <input type="time" id="recurring_monthly_start_time" name="recurring_monthly_start_time" value="<?php echo esc_attr($start_time); ?>" />
                    </div>
                    <div class="mj-form-field mj-form-field--inline">
                        <label for="recurring_monthly_end_time"><?php esc_html_e('Heure de fin', 'mj-member'); ?></label>
                        <input type="time" id="recurring_monthly_end_time" name="recurring_monthly_end_time" value="<?php echo esc_attr($end_time); ?>" />
                    </div>
                </div>
            </div>

            <div class="mj-form-field">
                <label for="recurring_until"><?php esc_html_e('Fin de récurrence', 'mj-member'); ?></label>
                <input type="date" id="recurring_until" name="recurring_until" value="<?php echo esc_attr($until_date); ?>" />
                <p class="description"><?php esc_html_e('Laisser vide pour une récurrence indéfinie', 'mj-member'); ?></p>
            </div>

            <!-- Champ caché pour stocker le payload JSON -->
            <input type="hidden" name="schedule_payload" value="<?php echo esc_attr(wp_json_encode($schedule_payload)); ?>" data-schedule-payload />
        </div>

        <?php
    }
}
