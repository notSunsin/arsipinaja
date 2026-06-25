@php
    $isAdmin = auth()->check() && auth()->user()->role_type === 'admin';

    if ($isAdmin) {
        $primaryColor = 'from-blue-600 to-indigo-700';
        $secondaryColor = 'from-indigo-600 to-blue-700';
        $accentColor = 'from-blue-500 to-indigo-600';
        $bgGradient = 'from-blue-50 to-indigo-50';
        $iconBg = 'from-blue-600 to-indigo-700';
    } else {
        $primaryColor = 'from-orange-600 to-amber-700';
        $secondaryColor = 'from-amber-600 to-orange-700';
        $accentColor = 'from-orange-500 to-amber-600';
        $bgGradient = 'from-orange-50 to-amber-50';
        $iconBg = 'from-orange-600 to-amber-700';
    }
@endphp

<div id="logoutConfirmModal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 backdrop-blur-sm hidden">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform scale-0 opacity-0 transition-all duration-700 ease-out"
        id="logoutModalContent">

        <div class="relative p-8 text-center overflow-hidden">
            <!-- Animated Background with Role Colors -->
            <div class="absolute inset-0 bg-gradient-to-br {{ $bgGradient }} opacity-10 rounded-2xl"></div>

            <!-- Floating Elements - Subtle and Professional -->
            <div class="absolute inset-0">
                <div class="absolute top-6 left-6 w-3 h-3 bg-gradient-to-r {{ $primaryColor }} rounded-full opacity-40 animate-pulse"></div>
                <div class="absolute top-12 right-8 w-2 h-2 bg-gradient-to-r {{ $secondaryColor }} rounded-full opacity-30 animate-ping"></div>
                <div class="absolute bottom-8 left-8 w-2.5 h-2.5 bg-gradient-to-r {{ $accentColor }} rounded-full opacity-35 animate-bounce"></div>
                <div class="absolute bottom-6 right-6 w-1.5 h-1.5 bg-gradient-to-r {{ $primaryColor }} rounded-full opacity-25 animate-pulse"></div>
            </div>

            <div class="relative z-10">
                <!-- Icon -->
                <div class="w-24 h-24 bg-gradient-to-br {{ $iconBg }} rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl transform scale-0"
                    id="logoutIcon">
                    <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-sign-out-alt text-2xl bg-gradient-to-r {{ $primaryColor }} bg-clip-text text-transparent"></i>
                    </div>
                </div>

                <h2 class="text-xl font-bold text-gray-800 mb-2 transform translate-y-8 opacity-0" id="logoutTitle">
                    Konfirmasi Logout
                </h2>

                <p class="text-gray-600 mb-8 transform translate-y-8 opacity-0" id="logoutMessage">
                    Apakah Anda yakin ingin keluar dari sistem ARSIPIN?
                </p>

                <div class="flex gap-3 transform translate-y-8 opacity-0" id="logoutActions">
                    <button type="button" onclick="closeLogoutModal()"
                        class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-semibold transition-all duration-300">
                        Batal
                    </button>
                    <button type="button" onclick="submitLogoutForm()"
                        class="flex-1 bg-gradient-to-r {{ $primaryColor }} hover:shadow-lg text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 hover:scale-105 transform">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Ya, Logout
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let logoutFormToSubmit = null;

    function openLogoutModal(form) {
        logoutFormToSubmit = form;

        const modal = document.getElementById('logoutConfirmModal');
        const content = document.getElementById('logoutModalContent');

        modal.classList.remove('hidden');

        requestAnimationFrame(() => {
            content.classList.remove('scale-0', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');

            setTimeout(() => {
                document.getElementById('logoutIcon').classList.remove('scale-0');
                document.getElementById('logoutIcon').classList.add('scale-100');
            }, 200);

            setTimeout(() => {
                document.getElementById('logoutTitle').classList.remove('translate-y-8', 'opacity-0');
                document.getElementById('logoutTitle').classList.add('translate-y-0', 'opacity-100');
            }, 350);

            setTimeout(() => {
                document.getElementById('logoutMessage').classList.remove('translate-y-8', 'opacity-0');
                document.getElementById('logoutMessage').classList.add('translate-y-0', 'opacity-100');
            }, 450);

            setTimeout(() => {
                document.getElementById('logoutActions').classList.remove('translate-y-8', 'opacity-0');
                document.getElementById('logoutActions').classList.add('translate-y-0', 'opacity-100');
            }, 550);
        });
    }

    function closeLogoutModal() {
        const modal = document.getElementById('logoutConfirmModal');
        const content = document.getElementById('logoutModalContent');

        content.classList.add('scale-0', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 500);

        logoutFormToSubmit = null;
    }

    function submitLogoutForm() {
        if (logoutFormToSubmit) {
            logoutFormToSubmit.submit();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('logoutConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogoutModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLogoutModal();
            }
        });
    });
</script>
