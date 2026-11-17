<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Página de confirmação de identidade e vínculo de CPF para contas OAuth2.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

global $USER, $DB, $CFG, $OUTPUT, $PAGE;

$returnurl = optional_param('returnurl', $CFG->wwwroot, PARAM_LOCALURL);
$action    = optional_param('action', '', PARAM_ALPHA);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/simple_sso/link_account.php', ['returnurl' => $returnurl]));
$PAGE->set_title('Validação de Identidade Obrigatória');
$PAGE->set_heading('Unificação Cadastral de Identidade');

// 1. Se o CPF já puder ser resolvido, redireciona imediatamente
$cpf_existente = null;
if (class_exists('\local_ponto_eletronico\identity_resolver')) {
    $cpf_existente = \local_ponto_eletronico\identity_resolver::resolve_cpf($USER);
}

if (!empty($cpf_existente)) {
    redirect(new moodle_url($returnurl));
}

$error_msg = '';

// 2. Processamento da vinculação do CPF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $cpf_input = optional_param('cpf_input', '', PARAM_RAW_TRIMMED);
    $cpf_limpo = preg_replace('/\D/', '', $cpf_input);

    if (strlen($cpf_limpo) !== 11) {
        $error_msg = 'Por favor, informe um número de CPF válido com 11 dígitos.';
    } else {
        // Grava no Moodle no campo idnumber do usuário
        $DB->set_field('user', 'idnumber', $cpf_limpo, ['id' => $USER->id]);
        $USER->idnumber = $cpf_limpo;

        // Grava na tabela de mapeamento de identidade
        if (class_exists('\local_ponto_eletronico\identity_resolver')) {
            \local_ponto_eletronico\identity_resolver::save_mapping(
                (int)$USER->id,
                $cpf_limpo,
                $USER->auth ?? 'oauth2',
                $USER->username ?? ''
            );
        }

        redirect(
            new moodle_url($returnurl),
            'Identidade confirmada com sucesso! Seu CPF foi vinculado à sua conta.',
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-sm border-0" style="border-radius: 8px;">
                <div class="card-header text-white py-3" style="background-color: #0c326f; border-radius: 8px 8px 0 0; color: #ffffff !important;">
                    <h5 class="mb-0 font-weight-bold" style="color: #ffffff !important;">
                        <i class="fa fa-shield-alt mr-2" style="color: #ffffff !important;"></i> Confirmação de Identidade Obrigatória
                    </h5>
                </div>
                <div class="card-body bg-light p-4">
                    <p>Para garantir que todas as suas marcações de ponto, abonos e requerimentos fiquem unificados em uma <strong>Pessoa Física Única</strong>, precisamos confirmar seu número de CPF.</p>

                    <div class="alert alert-info py-2 small border-0 mb-3" style="border-left: 4px solid #1351b4;">
                        <i class="fa fa-info-circle mr-1"></i> Seu CPF é utilizado exclusivamente como chave de unificação de dados com o sistema de RH da Prefeitura de Bertioga.
                    </div>

                    <?php if (!empty($error_msg)): ?>
                        <div class="alert alert-danger"><?php echo s($error_msg); ?></div>
                    <?php endif; ?>

                    <form method="post" action="link_account.php">
                        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                        <input type="hidden" name="returnurl" value="<?php echo s($returnurl); ?>">

                        <div class="form-group mb-4">
                            <label for="cpf_input" class="font-weight-bold small text-uppercase" style="color: #0c326f;">Informe seu CPF (11 dígitos): *</label>
                            <input type="text" name="cpf_input" id="cpf_input" class="form-control form-control-lg" placeholder="000.000.000-00" required maxlength="14" style="border-radius: 4px;">
                        </div>

                        <div class="form-group text-right mb-0">
                            <button type="submit" class="btn btn-success btn-lg font-weight-bold px-4 shadow-sm" style="background-color: #168821; border-color: #168821; border-radius: 20px; color: #ffffff !important;">
                                <i class="fa fa-check mr-1" style="color: #ffffff !important;"></i> Confirmar e Continuar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
