<?php
if (!defined('LAYOUT_BOOTSTRAPPED')) { return; } // garante ordem correta
?>

</div> <!-- End page-content -->
</div> <!-- End main-content -->

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    display: none;
"></div>

<!-- Footer -->
<footer style="
    margin-left: 260px;
    padding: 20px 25px;
    background: white;
    border-top: 1px solid #e9ecef;
    color: #666;
    font-size: 14px;
    transition: margin-left 0.3s ease;
" id="footer">
    <div style="display: flex; justify-content: between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            © 2025 <strong>VisitorFlow</strong> - Sistema de Gestão de Visitantes
        </div>
        <div style="display: flex; gap: 20px; margin-left: auto;">
            <a href="#" style="color: #666; text-decoration: none;">
                <i class="fas fa-shield-alt me-1"></i>
                Privacidade
            </a>
            <a href="#" style="color: #666; text-decoration: none;">
                <i class="fas fa-life-ring me-1"></i>
                Suporte
            </a>
            <a href="debug.php" style="color: #666; text-decoration: none;">
                <i class="fas fa-bug me-1"></i>
                Debug
            </a>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const footer = document.getElementById('footer');
    const mobileOverlay = document.getElementById('mobileOverlay');
    
    // Ajustar footer quando sidebar colapsa
    function adjustFooter() {
        if (window.innerWidth > 768) {
            if (sidebar.classList.contains('collapsed')) {
                footer.style.marginLeft = '70px';
            } else {
                footer.style.marginLeft = '260px';
            }
        } else {
            footer.style.marginLeft = '0';
        }
    }
    
    // Observer para mudanças na sidebar
    const observer = new MutationObserver(adjustFooter);
    observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    
    // Ajustar no resize
    window.addEventListener('resize', adjustFooter);
    adjustFooter();
    
    // Mobile overlay
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                mobileOverlay.style.display = sidebar.classList.contains('mobile-open') ? 'none' : 'block';
            }
        });
    }
    
    mobileOverlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-open');
        mobileOverlay.style.display = 'none';
    });
});

// PWA-like features
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        // Registrar service worker se necessário
    });
}

// Função para mostrar notificações
function showNotification(type, message) {
    const toastContainer = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-modern ${type}`;
    toast.innerHTML = `
        <div class="toast-body d-flex align-items-center gap-3">
            <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-circle' : 'info-circle')}"></i>
            <span>${message}</span>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, { delay: 4000 });
    bsToast.show();
    
    // Remove do DOM após esconder
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

// Função global para atualizar stats em tempo real
function updateStats() {
    // Implementar AJAX para atualizar estatísticas
    // fetch('api/stats.php').then(...)
}

// Auto-save de formulários (opcional)
function enableAutoSave() {
    const forms = document.querySelectorAll('form[data-autosave]');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('change', function() {
                const formData = new FormData(form);
                localStorage.setItem(`autosave_${form.id}`, JSON.stringify(Object.fromEntries(formData)));
            });
        });
        
        // Restaurar dados salvos
        const savedData = localStorage.getItem(`autosave_${form.id}`);
        if (savedData) {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) input.value = data[key];
            });
        }
    });
}

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl+N para novo visitante
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        const modal = document.getElementById('modalInvite');
        if (modal) {
            new bootstrap.Modal(modal).show();
        }
    }
    
    // Ctrl+F para busca
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
    
    // Esc para fechar modais
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            bootstrap.Modal.getInstance(modal)?.hide();
        });
    }
});

// Função para copiar texto (útil para links de convite)
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showNotification('success', 'Copiado para a área de transferência!');
    }, function() {
        showNotification('danger', 'Erro ao copiar. Tente novamente.');
    });
}

// Função para download de dados
function downloadData(type = 'csv') {
    // Implementar download de relatórios
    showNotification('info', 'Gerando arquivo para download...');
}

console.log('🚀 VisitorFlow - Sistema carregado com sucesso!');
</script>

</body>
</html>