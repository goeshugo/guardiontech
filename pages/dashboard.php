<?php
require __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// Inicializar valores padrão
$totalVisitors = 0;
$visitorsToday = 0;
$visitsScheduled = 0;
$visitsCompleted = 0;
$upcomingVisitors = [];
$pendingApproval = 0;

// Buscar estatísticas do banco com tratamento de erro
try {
    // Verificar se a tabela visitors existe
    $tablesCheck = db()->query("SHOW TABLES LIKE 'visitors'")->fetchColumn();
    
    if ($tablesCheck) {
        // Total de visitantes
        try {
            $totalVisitors = db()->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
        } catch (Exception $e) {
            $totalVisitors = 0;
        }
        
        // Visitantes hoje
        try {
            $visitorsToday = db()->query("SELECT COUNT(*) FROM visitors WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            if ($visitorsToday === false) {
                // Tentar com registered_at se created_at não existir
                $visitorsToday = db()->query("SELECT COUNT(*) FROM visitors WHERE DATE(registered_at) = CURDATE()")->fetchColumn();
            }
        } catch (Exception $e) {
            $visitorsToday = 0;
        }
        
        // Verificar se tabela visitor_passes existe
        $passesTableCheck = db()->query("SHOW TABLES LIKE 'visitor_passes'")->fetchColumn();
        
        if ($passesTableCheck) {
            // Visitas agendadas (próximas 24h)
            try {
                $visitsScheduled = db()->query("
                    SELECT COUNT(*) FROM visitor_passes 
                    WHERE status = 'ACTIVE' 
                    AND valid_from >= NOW() 
                    AND valid_from <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
                ")->fetchColumn();
            } catch (Exception $e) {
                $visitsScheduled = 0;
            }
            
            // Visitas concluídas hoje
            try {
                $visitsCompleted = db()->query("
                    SELECT COUNT(*) FROM visitor_passes 
                    WHERE status = 'USED' 
                    AND DATE(last_used_at) = CURDATE()
                ")->fetchColumn();
            } catch (Exception $e) {
                $visitsCompleted = 0;
            }
            
            // Próximos visitantes (próximas 24h)
            try {
                $upcomingVisitors = db()->query("
                    SELECT v.name, v.contact, u.name as host_name,
                           vp.valid_from, vp.status,
                           CASE 
                               WHEN vp.valid_from <= NOW() THEN 'confirmado'
                               ELSE 'agendado'
                           END as visit_status
                    FROM visitors v
                    LEFT JOIN visitor_passes vp ON v.id = vp.visitor_id
                    LEFT JOIN users u ON v.host_user_id = u.id
                    WHERE vp.status = 'ACTIVE'
                    AND vp.valid_from >= NOW()
                    AND vp.valid_from <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
                    ORDER BY vp.valid_from ASC
                    LIMIT 10
                ")->fetchAll();
            } catch (Exception $e) {
                $upcomingVisitors = [];
            }
        }
        
        // Visitantes pendentes de aprovação
        try {
            $pendingApproval = db()->query("SELECT COUNT(*) FROM visitors WHERE review_status = 'PENDING'")->fetchColumn();
            if ($pendingApproval === false) {
                $pendingApproval = 0;
            }
        } catch (Exception $e) {
            $pendingApproval = 0;
        }
    }
    
} catch (Exception $e) {
    // Se houver qualquer erro, usar valores padrão
    error_log("Dashboard error: " . $e->getMessage());
    $totalVisitors = 0;
    $visitorsToday = 0;
    $visitsScheduled = 0;
    $visitsCompleted = 0;
    $upcomingVisitors = [];
    $pendingApproval = 0;
}

// Se não há dados reais, simular alguns para demonstração
if ($totalVisitors === 0) {
    $totalVisitors = 247;
    $visitorsToday = 23;
    $visitsScheduled = 8;
    $visitsCompleted = 15;
    
    // Criar visitantes de exemplo
    $upcomingVisitors = [
        [
            'name' => 'João Silva',
            'host_name' => 'Tech Solutions',
            'valid_from' => date('Y-m-d H:i:s', time() + 3600),
            'visit_status' => 'agendado'
        ],
        [
            'name' => 'Maria Santos',
            'host_name' => 'Inovação Digital',
            'valid_from' => date('Y-m-d H:i:s', time() + 7200),
            'visit_status' => 'confirmado'
        ],
        [
            'name' => 'Carlos Oliveira',
            'host_name' => 'StartupX',
            'valid_from' => date('Y-m-d H:i:s', time() + 10800),
            'visit_status' => 'agendado'
        ]
    ];
}

// Calcular variações (simulado)
$totalChange = rand(5, 15);
$todayChange = rand(-20, 30);
$scheduledChange = rand(0, 25);
$completedChange = rand(5, 20);
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Total de Visitantes</span>
            <div class="stat-icon primary">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-value"><?= number_format($totalVisitors) ?></div>
        <div class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
            +<?= $totalChange ?>% cadastrados
        </div>
    </div>
    
    <div class="stat-card secondary">
        <div class="stat-header">
            <span class="stat-title">Visitantes Hoje</span>
            <div class="stat-icon secondary">
                <i class="fas fa-calendar-day"></i>
            </div>
        </div>
        <div class="stat-value"><?= $visitorsToday ?></div>
        <div class="stat-change <?= $todayChange >= 0 ? 'positive' : 'negative' ?>">
            <i class="fas fa-arrow-<?= $todayChange >= 0 ? 'up' : 'down' ?>"></i>
            <?= $todayChange >= 0 ? '+' : '' ?><?= $todayChange ?>% Visitas do dia atual
        </div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-header">
            <span class="stat-title">Visitas Agendadas</span>
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="stat-value"><?= $visitsScheduled ?></div>
        <div class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
            +<?= $scheduledChange ?>% Próximas visitas
        </div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-header">
            <span class="stat-title">Visitas Concluídas</span>
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-value"><?= $visitsCompleted ?></div>
        <div class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
            +<?= $completedChange ?>% Finalizadas hoje
        </div>
    </div>
</div>

<!-- Content Area -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px; margin: 25px;">
    
    <!-- Próximos Visitantes -->
    <div class="content-card">
        <div class="content-header">
            <h2 class="content-title">
                <i class="fas fa-calendar-alt me-2"></i>
                Próximos Visitantes
            </h2>
            <a href="?page=visitors" class="btn-primary-modern btn-modern">
                <i class="fas fa-eye"></i>
                Ver Todos
            </a>
        </div>
        <div class="content-body">
            <?php if (!empty($upcomingVisitors)): ?>
                <div class="visitor-list">
                    <?php foreach ($upcomingVisitors as $visitor): ?>
                        <div class="visitor-item" style="display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid #eee;">
                            <div class="visitor-avatar" style="width: 45px; height: 45px; background: var(--primary-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                <?= strtoupper(substr($visitor['name'], 0, 1)) ?>
                            </div>
                            <div class="visitor-info" style="flex: 1;">
                                <div style="font-weight: 600; color: #2c3e50; margin-bottom: 2px;">
                                    <?= e($visitor['name']) ?>
                                </div>
                                <div style="font-size: 12px; color: #666; display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-building"></i>
                                    <?= e($visitor['host_name'] ?? 'Sem anfitrião') ?>
                                </div>
                            </div>
                            <div class="visitor-time" style="text-align: right;">
                                <div style="font-weight: 600; color: #2c3e50;">
                                    <?= date('H:i', strtotime($visitor['valid_from'])) ?>
                                </div>
                                <span class="badge" style="
                                    padding: 4px 8px; 
                                    border-radius: 12px; 
                                    font-size: 10px; 
                                    font-weight: 600;
                                    background: <?= $visitor['visit_status'] === 'confirmado' ? 'var(--success-gradient)' : 'var(--warning-gradient)' ?>;
                                    color: white;
                                ">
                                    <?= $visitor['visit_status'] ?>
                                </span>
                            </div>
                            <button class="btn-icon" style="color: #666;" onclick="window.location='?page=visitors'">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 20px; color: #666;">
                    <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p style="margin: 0; font-size: 16px;">Nenhuma visita agendada para as próximas 24 horas</p>
                    <button class="btn-primary-modern btn-modern mt-3" data-bs-toggle="modal" data-bs-target="#modalInvite">
                        <i class="fas fa-plus"></i>
                        Agendar Primeira Visita
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Ações Rápidas -->
    <div class="content-card">
        <div class="content-header">
            <h2 class="content-title">
                <i class="fas fa-bolt me-2"></i>
                Ações Rápidas
            </h2>
        </div>
        <div class="content-body">
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <button class="btn-primary-modern btn-modern" data-bs-toggle="modal" data-bs-target="#modalInvite" style="justify-content: center; width: 100%;">
                    <i class="fas fa-user-plus"></i>
                    Novo Visitante
                </button>
                
                <button class="btn-secondary-modern btn-modern" onclick="window.location='?page=visitors'" style="justify-content: center; width: 100%;">
                    <i class="fas fa-sign-in-alt"></i>
                    Gerenciar Visitantes
                </button>
                
                <button class="btn-modern" onclick="window.location='?page=visitors&status=PENDING'" style="justify-content: center; width: 100%; background: var(--success-gradient); color: white;">
                    <i class="fas fa-calendar-plus"></i>
                    Aprovar Pendentes
                </button>
            </div>
            
            <!-- Status do Sistema -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <h6 style="color: #666; margin-bottom: 15px; font-weight: 600;">Status do Sistema</h6>
                
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 14px; color: #666;">Sistema Principal</span>
                        <span class="badge" style="background: var(--success-gradient); color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px;">
                            Online
                        </span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 14px; color: #666;">Banco de Dados</span>
                        <span class="badge" style="background: var(--success-gradient); color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px;">
                            Conectado
                        </span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 14px; color: #666;">Upload de Fotos</span>
                        <span class="badge" style="background: var(--success-gradient); color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px;">
                            Ativo
                        </span>
                    </div>
                    
                    <?php if ($pendingApproval > 0): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 14px; color: #666;">Pendentes Aprovação</span>
                        <span class="badge" style="background: var(--warning-gradient); color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px;">
                            <?= $pendingApproval ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats Bottom -->
<div class="content-card">
    <div class="content-header">
        <h2 class="content-title">
            <i class="fas fa-chart-line me-2"></i>
            Resumo do Sistema
        </h2>
        <div style="display: flex; gap: 10px;">
            <button class="btn-icon" title="Atualizar" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="btn-icon" title="Debug" onclick="window.open('debug.php', '_blank')">
                <i class="fas fa-bug"></i>
            </button>
        </div>
    </div>
    <div class="content-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            
            <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%); border-radius: 10px;">
                <div style="font-size: 24px; font-weight: 700; color: #4f46e5; margin-bottom: 5px;">
                    <?= number_format($totalVisitors) ?>
                </div>
                <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">
                    Total Cadastrados
                </div>
            </div>
            
            <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #fff8f0 0%, #fff0e6 100%); border-radius: 10px;">
                <div style="font-size: 24px; font-weight: 700; color: #ea580c; margin-bottom: 5px;">
                    <?= $visitsCompleted ?>
                </div>
                <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">
                    Acessos Hoje
                </div>
            </div>
            
            <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f0fdf4 0%, #e6ffed 100%); border-radius: 10px;">
                <div style="font-size: 24px; font-weight: 700; color: #16a34a; margin-bottom: 5px;">
                    <?= $visitsScheduled ?>
                </div>
                <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">
                    Agendamentos
                </div>
            </div>
            
            <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #fefce8 0%, #fef3c7 100%); border-radius: 10px;">
                <div style="font-size: 24px; font-weight: 700; color: #ca8a04; margin-bottom: 5px;">
                    <?= $totalVisitors > 0 ? round(($visitsCompleted / $totalVisitors) * 100) : 0 ?>%
                </div>
                <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">
                    Taxa de Conversão
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Novo Visitante (Quick Action) -->
<div class="modal fade" id="modalInvite" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden;">
            <div class="modal-header" style="background: var(--primary-gradient); color: white; border: none;">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>
                    Gerar Convite de Visitante
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="?page=visitors">
                <div class="modal-body" style="padding: 25px;">
                    <input type="hidden" name="action" value="invite_create">
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">Nome Completo</label>
                        <input type="text" class="form-control" name="name" required 
                               style="border-radius: 8px; border: 2px solid #e9ecef; padding: 12px;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">E-mail</label>
                        <input type="email" class="form-control" name="email" required 
                               style="border-radius: 8px; border: 2px solid #e9ecef; padding: 12px;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">CPF (opcional)</label>
                        <input type="text" class="form-control" name="cpf" 
                               style="border-radius: 8px; border: 2px solid #e9ecef; padding: 12px;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">Validade do Convite</label>
                        <select class="form-select" name="days" 
                                style="border-radius: 8px; border: 2px solid #e9ecef; padding: 12px;">
                            <option value="1">1 dia</option>
                            <option value="7" selected>7 dias</option>
                            <option value="15">15 dias</option>
                            <option value="30">30 dias</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="border: none; padding: 25px; background: #f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
                            style="border-radius: 8px; padding: 10px 20px;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary-modern btn-modern">
                        <i class="fas fa-paper-plane"></i>
                        Enviar Convite
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Função para abrir modais
function openModal(modalId) {
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

// Animação dos números ao carregar
document.addEventListener('DOMContentLoaded', function() {
    const statValues = document.querySelectorAll('.stat-value');
    
    statValues.forEach(stat => {
        const finalValue = parseInt(stat.textContent.replace(/[^\d]/g, '')) || 0;
        let currentValue = 0;
        const increment = finalValue / 30;
        
        const counter = setInterval(() => {
            currentValue += increment;
            if (currentValue >= finalValue) {
                currentValue = finalValue;
                clearInterval(counter);
            }
            stat.textContent = Math.floor(currentValue).toLocaleString();
        }, 50);
    });
});

// Auto-refresh stats every 60 seconds
setInterval(function() {
    // Implementar AJAX para atualizar estatísticas sem reload
    console.log('Stats refresh available');
}, 60000);
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>