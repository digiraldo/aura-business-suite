<?php
/**
 * Template: Formulario Público de Inscripción
 * Usado por shortcode [aura_enrollment_form type="student|volunteer|teacher|participant|intern"]
 *
 * Variables disponibles:
 *  $form_type      — 'student'|'volunteer'|'teacher'|'participant'|'intern'|'' (libre)
 *  $areas          — array de objetos {id, name} de wp_aura_areas activas tipo 'program'
 *  $nonce          — Nonce de seguridad
 *  $rate_blocked   — bool: si el IP ha excedido el límite de envíos
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$type_labels = [
    'student'     => __( 'Estudiante', 'aura-suite' ),
    'volunteer'   => __( 'Voluntario', 'aura-suite' ),
    'teacher'     => __( 'Instructor / Facilitador', 'aura-suite' ),
    'participant' => __( 'Participante', 'aura-suite' ),
    'intern'      => __( 'Practicante / Pasante', 'aura-suite' ),
];
?>
<div class="aura-portal-wrap aura-enroll-wrap">

    <?php if ( $rate_blocked ) : ?>
        <div class="aura-notice-front aura-notice-error">
            <?php esc_html_e( 'Has enviado demasiadas solicitudes. Por favor espera 24 horas antes de intentarlo nuevamente.', 'aura-suite' ); ?>
        </div>
    <?php else : ?>

    <div id="aura-enroll-success" class="aura-enroll-success" style="display:none;">
        <span class="aura-success-icon">🎉</span>
        <h3><?php esc_html_e( '¡Solicitud enviada exitosamente!', 'aura-suite' ); ?></h3>
        <p><?php esc_html_e( 'Hemos recibido tu solicitud. Te contactaremos pronto para informarte sobre el proceso de ingreso.', 'aura-suite' ); ?></p>
    </div>

    <form id="aura-enrollment-form" class="aura-enrollment-form" novalidate style="">

        <!-- ── Tipo de perfil (visible si no se forzó) ── -->
        <?php if ( empty( $form_type ) ) : ?>
        <div class="aura-enroll-section">
            <h3 class="aura-section-title"><?php esc_html_e( 'Tipo de solicitud', 'aura-suite' ); ?></h3>
            <div class="aura-profile-type-group">
                <?php foreach ( $type_labels as $key => $label ) : ?>
                <label class="aura-radio-card">
                    <input type="radio" name="profile_type" value="<?php echo esc_attr( $key ); ?>"
                           <?php checked( $key, 'student' ); ?> />
                    <span><?php echo esc_html( $label ); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else : ?>
        <input type="hidden" name="profile_type" value="<?php echo esc_attr( $form_type ); ?>" />
        <div class="aura-enroll-type-label">
            <span><?php esc_html_e( 'Solicitud como:', 'aura-suite' ); ?></span>
            <strong><?php echo esc_html( $type_labels[ $form_type ] ?? ucfirst( $form_type ) ); ?></strong>
        </div>
        <?php endif; ?>

        <!-- ── Sección 1: Datos personales ── -->
        <div class="aura-enroll-section">
            <h3 class="aura-section-title">
                <span class="section-num">1</span>
                <?php esc_html_e( 'Información personal', 'aura-suite' ); ?>
            </h3>

            <div class="aura-field-row">
                <div class="aura-field-group aura-field-half">
                    <label for="enroll-first-name">
                        <?php esc_html_e( 'Nombre', 'aura-suite' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" id="enroll-first-name" name="first_name"
                           class="aura-input"
                           placeholder="<?php esc_attr_e( 'Tu nombre', 'aura-suite' ); ?>"
                           maxlength="150" required />
                </div>
                <div class="aura-field-group aura-field-half">
                    <label for="enroll-last-name">
                        <?php esc_html_e( 'Apellido', 'aura-suite' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" id="enroll-last-name" name="last_name"
                           class="aura-input"
                           placeholder="<?php esc_attr_e( 'Tu apellido', 'aura-suite' ); ?>"
                           maxlength="150" required />
                </div>
            </div>

            <div class="aura-field-row">
                <div class="aura-field-group aura-field-half">
                    <label for="enroll-email">
                        <?php esc_html_e( 'Correo electrónico', 'aura-suite' ); ?> <span class="required">*</span>
                    </label>
                    <input type="email" id="enroll-email" name="email"
                           class="aura-input"
                           placeholder="<?php esc_attr_e( 'correo@ejemplo.com', 'aura-suite' ); ?>"
                           maxlength="254" required />
                </div>
                <div class="aura-field-group aura-field-half">
                    <label for="enroll-phone">
                        <?php esc_html_e( 'Teléfono (WhatsApp)', 'aura-suite' ); ?>
                    </label>
                    <input type="tel" id="enroll-phone" name="phone"
                           class="aura-input"
                           placeholder="<?php esc_attr_e( '+1 555 000 0000', 'aura-suite' ); ?>"
                           maxlength="30" />
                </div>
            </div>

            <div class="aura-field-row">
                <div class="aura-field-group aura-field-half">
                    <label for="enroll-id-number">
                        <?php esc_html_e( 'Cédula / Pasaporte / DNI', 'aura-suite' ); ?>
                    </label>
                    <input type="text" id="enroll-id-number" name="id_number"
                           class="aura-input"
                           placeholder="<?php esc_attr_e( 'Número de documento', 'aura-suite' ); ?>"
                           maxlength="50" />
                </div>
                <div class="aura-field-group aura-field-half">
                    <label for="enroll-birthdate">
                        <?php esc_html_e( 'Fecha de nacimiento', 'aura-suite' ); ?>
                    </label>
                    <input type="date" id="enroll-birthdate" name="birthdate"
                           class="aura-input" />
                </div>
            </div>

            <div class="aura-field-row">
                <div class="aura-field-group aura-field-half">
                    <label for="enroll-gender"><?php esc_html_e( 'Género', 'aura-suite' ); ?></label>
                    <select id="enroll-gender" name="gender" class="aura-input">
                        <option value=""><?php esc_html_e( '— Selecciona —', 'aura-suite' ); ?></option>
                        <option value="M"><?php esc_html_e( 'Masculino', 'aura-suite' ); ?></option>
                        <option value="F"><?php esc_html_e( 'Femenino', 'aura-suite' ); ?></option>
                        <option value="otro"><?php esc_html_e( 'Otro', 'aura-suite' ); ?></option>
                        <option value="prefiero_no_decir"><?php esc_html_e( 'Prefiero no decir', 'aura-suite' ); ?></option>
                    </select>
                </div>
                <div class="aura-field-group aura-field-half">
                    <label for="enroll-city"><?php esc_html_e( 'Ciudad', 'aura-suite' ); ?></label>
                    <input type="text" id="enroll-city" name="city"
                           class="aura-input"
                           placeholder="<?php esc_attr_e( 'Tu ciudad', 'aura-suite' ); ?>"
                           maxlength="100" />
                </div>
            </div>
        </div>

        <!-- ── Sección 2: Áreas de interés ── -->
        <?php if ( ! empty( $areas ) ) : ?>
        <div class="aura-enroll-section">
            <h3 class="aura-section-title">
                <span class="section-num">2</span>
                <?php esc_html_e( '¿En qué área(s) o programa(s) te interesa participar?', 'aura-suite' ); ?>
            </h3>
            <?php if ( count( $areas ) === 1 ) : ?>
                <input type="hidden" name="preferred_areas[]" value="<?php echo esc_attr( $areas[0]->id ); ?>" />
                <p class="aura-areas-single">
                    <?php echo esc_html( $areas[0]->name ); ?>
                    <span class="aura-areas-auto"><?php esc_html_e( '(preseleccionada automáticamente)', 'aura-suite' ); ?></span>
                </p>
            <?php else : ?>
                <div class="aura-areas-grid">
                    <?php foreach ( $areas as $area ) : ?>
                    <label class="aura-area-checkbox">
                        <input type="checkbox" name="preferred_areas[]" value="<?php echo esc_attr( $area->id ); ?>" />
                        <?php echo esc_html( $area->name ); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── Sección 3: Datos de la solicitud ── -->
        <div class="aura-enroll-section">
            <h3 class="aura-section-title">
                <span class="section-num"><?php echo ( ! empty( $areas ) ) ? 3 : 2; ?></span>
                <?php esc_html_e( 'Información de tu solicitud', 'aura-suite' ); ?>
            </h3>

            <div class="aura-field-group">
                <label for="enroll-motivation">
                    <?php esc_html_e( '¿Por qué quieres ingresar al programa?', 'aura-suite' ); ?>
                    <span class="required">*</span>
                </label>
                <textarea id="enroll-motivation" name="motivation"
                          class="aura-input aura-textarea"
                          rows="3"
                          placeholder="<?php esc_attr_e( 'Cuéntanos tu motivación para unirte…', 'aura-suite' ); ?>"
                          required></textarea>
            </div>

            <div class="aura-field-group">
                <label for="enroll-supported-by">
                    <?php esc_html_e( '¿Quién te apoya o recomienda?', 'aura-suite' ); ?>
                </label>
                <input type="text" id="enroll-supported-by" name="supported_by"
                       class="aura-input"
                       placeholder="<?php esc_attr_e( 'Nombre de quien te refiere (opcional)', 'aura-suite' ); ?>"
                       maxlength="300" />
            </div>

            <div class="aura-field-group">
                <label for="enroll-talent">
                    <?php esc_html_e( '¿Qué talento o habilidad tienes?', 'aura-suite' ); ?>
                </label>
                <textarea id="enroll-talent" name="talent"
                          class="aura-input aura-textarea"
                          rows="2"
                          placeholder="<?php esc_attr_e( 'Música, arte, deporte, tecnología…', 'aura-suite' ); ?>"></textarea>
            </div>

            <div class="aura-field-group">
                <label for="enroll-experience">
                    <?php esc_html_e( 'Experiencia previa relevante', 'aura-suite' ); ?>
                </label>
                <textarea id="enroll-experience" name="experience"
                          class="aura-input aura-textarea"
                          rows="2"
                          placeholder="<?php esc_attr_e( 'Cursos, talleres u otras experiencias previas…', 'aura-suite' ); ?>"></textarea>
            </div>

            <div class="aura-field-group">
                <label for="enroll-extra"><?php esc_html_e( 'Información adicional', 'aura-suite' ); ?></label>
                <textarea id="enroll-extra" name="extra_info"
                          class="aura-input aura-textarea"
                          rows="2"
                          placeholder="<?php esc_attr_e( '¿Algo más que quieras que sepamos?', 'aura-suite' ); ?>"></textarea>
            </div>
        </div>

        <!-- Términos (anti-spam honeypot) -->
        <div class="aura-hp-field" aria-hidden="true">
            <input type="text" name="hp_field" tabindex="-1" autocomplete="off" />
        </div>

        <div id="aura-enroll-notice" class="aura-notice-front" style="display:none;"></div>

        <div class="aura-enroll-footer">
            <button type="submit" id="aura-enroll-btn" class="aura-btn aura-btn-primary aura-btn-lg">
                <?php esc_html_e( 'Enviar solicitud', 'aura-suite' ); ?>
            </button>
            <p class="aura-enroll-disclaimer">
                <?php esc_html_e( 'Tu información es confidencial y será usada únicamente para el proceso de inscripción.', 'aura-suite' ); ?>
            </p>
        </div>

    </form>

    <?php endif; // !rate_blocked ?>

</div><!-- /aura-portal-wrap -->
