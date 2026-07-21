<?php
$success    = false;
$error      = null;
$savedPets  = [];
$savedOwner = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/twenty.php';

    $owner = [
        'nome'       => trim($_POST['nome']        ?? ''),
        'email'      => trim($_POST['email']       ?? ''),
        'phone'      => trim($_POST['phone']       ?? ''),
        'numero_pets'=> (int)($_POST['numero_pets'] ?? 1),
    ];

    $rawPets    = $_POST['pets'] ?? [];
    $pets       = [];
    $hasError   = false;

    // Validate owner
    if (empty($owner['nome']) || empty($owner['email'])) {
        $hasError = true;
        $error    = 'Por favor, preencha seu nome e e-mail.';
    } elseif (!filter_var($owner['email'], FILTER_VALIDATE_EMAIL)) {
        $hasError = true;
        $error    = 'Por favor, insira um e-mail válido.';
    }

    // Validate and collect each pet
    if (!$hasError) {
        $numPets = max(1, $owner['numero_pets']);
        for ($i = 0; $i < $numPets; $i++) {
            $p = $rawPets[$i] ?? [];
            $rawBirthday = trim($p['birthday'] ?? '');
            $idade = 0;
            if ($rawBirthday) {
                $birthYear  = (int)substr($rawBirthday, 0, 4);
                $birthMonth = (int)substr($rawBirthday, 5, 2);
                $birthDay   = (int)substr($rawBirthday, 8, 2);
                $nowYear    = (int)date('Y');
                $nowMonth   = (int)date('m');
                $nowDay     = (int)date('d');
                $idade = $nowYear - $birthYear;
                if ($nowMonth < $birthMonth || ($nowMonth === $birthMonth && $nowDay < $birthDay)) {
                    $idade--;
                }
                $idade = max(0, $idade);
            }

            $pet = [
                'nome'                    => trim($p['nome']           ?? ''),
                'birthday'                => $rawBirthday,
                'porte'                   => trim($p['porte']          ?? ''),
                'condicao_saude'          => trim($p['condicao_saude'] ?? ''),
                'motivo'                  => trim($p['motivo']         ?? ''),
                'primeira_vez_condropure' => !empty($p['primeira_vez_condropure']),
                'idade'                   => $idade,
            ];

            $petLabel = $numPets > 1 ? ($i + 1) . 'º pet' : 'pet';
            if (empty($pet['nome']) || empty($pet['birthday']) || empty($pet['porte'])
                || empty($pet['condicao_saude']) || empty($pet['motivo'])) {
                $hasError = true;
                $error    = "Por favor, preencha todos os campos obrigatórios do $petLabel.";
                break;
            }
            $pets[] = $pet;
            $savedPets[] = array_merge($pet, ['primeira_vez' => $pet['primeira_vez_condropure']]);
        }
        $savedOwner = $owner;
    }

    if (!$hasError) {
        $result = sendToTwenty($owner, $pets);
        if ($result['success']) {
            $success = true;
        } else {
            $detailMessage = $result['message'] ?? '';
            if (!empty($result['details'])) {
                if (is_array($result['details'])) {
                    $errorDetails = [];
                    foreach ($result['details'] as $key => $value) {
                        if ($key !== 'message' && $key !== 'success') {
                            if (is_array($value)) {
                                $errorDetails[] = "{$key}: " . json_encode($value);
                            } else {
                                $errorDetails[] = "{$key}: {$value}";
                            }
                        }
                    }
                    if (!empty($errorDetails)) {
                        $detailMessage .= ' [' . implode(' | ', $errorDetails) . ']';
                    }
                } else {
                    $detailMessage .= ' ' . $result['details'];
                }
            }
            $error = 'Ocorreu um erro ao enviar os dados. Por favor, tente novamente. ' . $detailMessage;
        }
    }
}

$numPets    = (int)($_POST['numero_pets'] ?? 1);
$savedPetsJson = json_encode($savedPets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Condropure — Cadastro do Seu Pet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/style.css">
    <link rel="icon" type="image/png" href="//petvi.com.br/cdn/shop/files/Frame_48096321.png?crop=center&height=32&v=1761858834&width=32">
</head>
<body>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="badge-pill">Cenário do Teste</div>
        <h1>Condropure</h1>
        <p class="hero-subtitle">Você acabou de fazer a Assinatura do Kit 2 Condropure. E seguiu para o próximo passo.</p>
        <a href="#cadastro" class="btn-hero">Cadastrar Meu Pet</a>
    </div>
</section>

<!-- Reasons Section -->
<section class="reasons">
    <div class="container">
        <h2 class="section-title">Por que escolher Condropure?</h2>
        <p class="section-subtitle">Desenvolvido por veterinários, testado com amor por pets de todo o Brasil</p>
        <div class="reasons-grid">
            <div class="reason-card">
                <div class="reason-icon">🧬</div>
                <h3>Fórmula Avançada</h3>
                <p>Combinação exclusiva de condroitina, glucosamina e colágeno tipo II para máxima eficácia.</p>
            </div>
            <div class="reason-card">
                <div class="reason-icon">⚡</div>
                <h3>Ação Rápida</h3>
                <p>Resultados visíveis em apenas 30 dias de uso contínuo, com melhora progressiva.</p>
            </div>
            <div class="reason-card">
                <div class="reason-icon">🌿</div>
                <h3>100% Natural</h3>
                <p>Ingredientes naturais certificados, sem corantes artificiais ou conservantes.</p>
            </div>
            <div class="reason-card">
                <div class="reason-icon">🐕</div>
                <h3>Para Todas as Raças</h3>
                <p>Formulado para cães e gatos de todos os portes e idades, do filhote ao idoso.</p>
            </div>
            <div class="reason-card">
                <div class="reason-icon">🏆</div>
                <h3>Premiado</h3>
                <p>Reconhecido pelo CRMV como o melhor suplemento articular para pets de 2023.</p>
            </div>
            <div class="reason-card">
                <div class="reason-icon">❤️</div>
                <h3>Aprovado pelos Pets</h3>
                <p>Sabor irresistível que os pets adoram. Sem stress na hora de dar o suplemento.</p>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="testimonials">
    <div class="container">
        <h2 class="section-title light">O que os tutores dizem</h2>
        <div class="testimonials-grid">
            <div class="testimonial-card">
                <div class="stars">★★★★★</div>
                <p>"Minha labrador de 10 anos voltou a correr no quintal! Condropure transformou a qualidade de vida dela."</p>
                <div class="testimonial-author">
                    <div class="author-avatar">MA</div>
                    <div><strong>Maria A.</strong><span>Tutora da Mel, 10 anos</span></div>
                </div>
            </div>
            <div class="testimonial-card">
                <div class="stars">★★★★★</div>
                <p>"Em 3 semanas já percebi meu golden retriever subindo as escadas sem dificuldade. Produto incrível!"</p>
                <div class="testimonial-author">
                    <div class="author-avatar">JP</div>
                    <div><strong>João P.</strong><span>Tutor do Thor, 8 anos</span></div>
                </div>
            </div>
            <div class="testimonial-card">
                <div class="stars">★★★★★</div>
                <p>"Meu veterinário indicou e não me arrependo. Minha gata ficou muito mais ativa e bem-disposta."</p>
                <div class="testimonial-author">
                    <div class="author-avatar">CS</div>
                    <div><strong>Carla S.</strong><span>Tutora da Luna, 7 anos</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Form Section -->
<section class="form-section" id="cadastro">
    <div class="container form-container">
        <div class="form-wrapper">
            <div class="form-header">
                <div class="form-badge">📋 Cadastro Gratuito</div>
                <h2>Cadastre o Seu Pet</h2>
                <p>Preencha o formulário abaixo para recebermos os dados do seu pet e personalizarmos a melhor solução para ele.</p>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success" style="margin: 48px 48px 0;">
                <span class="alert-icon">✅</span>
                <div>
                    <strong>Cadastro realizado com sucesso!</strong>
                    <p>Recebemos os dados do<?= count($pets) > 1 ? 's seus pets' : ' seu pet' ?>. Em breve entraremos em contato com as melhores recomendações personalizadas.</p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error" style="margin: 24px 48px 0;">
                <span class="alert-icon">⚠️</span>
                <div>
                    <strong>Atenção</strong>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="#cadastro" class="pet-form" novalidate>

                <!-- ① OWNER DATA -->
                <div class="form-section-label">
                    <span class="section-number">1</span> Dados do Tutor
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Seu nome completo <span class="required">*</span></label>
                        <input type="text" id="nome" name="nome" placeholder="Ex: Maria Silva"
                               value="<?= htmlspecialchars($savedOwner['nome'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Seu e-mail <span class="required">*</span></label>
                        <input type="email" id="email" name="email" placeholder="Ex: maria@email.com"
                               value="<?= htmlspecialchars($savedOwner['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Seu telefone / WhatsApp</label>
                        <input type="tel" id="phone" name="phone" placeholder="Ex: +55 11 99999-9999"
                               value="<?= htmlspecialchars($savedOwner['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="numero_pets">Quantos pets você quer cadastrar? <span class="required">*</span></label>
                        <select id="numero_pets" name="numero_pets" required>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= $numPets === $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- ② PET DATA (rendered by JS) -->
                <div class="form-section-label" style="margin-top: 32px;">
                    <span class="section-number">2</span> Dados do<?= $numPets > 1 ? 's Pets' : ' Pet' ?>
                </div>

                <div id="pets-container">
                    <!-- Filled dynamically by script.js -->
                </div>

                <button type="submit" class="btn-submit">
                    <span>Cadastrar <?= $numPets > 1 ? 'Meus Pets' : 'Meu Pet' ?> Agora</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>

                <p class="form-disclaimer">🔒 Seus dados estão protegidos. Não compartilhamos informações com terceiros.</p>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <p>© <?= date('Y') ?> Condropure — Todos os direitos reservados.</p>
        <p>Suplemento alimentar para cães e gatos. Consulte sempre o seu veterinário.</p>
    </div>
</footer>

<script>
    window.__SAVED_PETS__  = <?= $savedPetsJson ?>;
</script>
<script src="public/script.js"></script>
</body>
</html>
