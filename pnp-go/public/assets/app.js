document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === '1') {
                return;
            }

            event.preventDefault();

            const icon = form.dataset.confirmIcon || 'question';
            const title = form.dataset.confirmTitle || 'ยืนยันการดำเนินการ';
            const text = form.dataset.confirmText || 'ต้องการดำเนินการต่อหรือไม่';
            const confirmButtonText = form.dataset.confirmButton || 'ยืนยัน';

            if (typeof Swal === 'undefined') {
                if (window.confirm(`${title}\n${text}`)) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
                return;
            }

            Swal.fire({
                title,
                text,
                icon,
                showCancelButton: true,
                confirmButtonText,
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#16406f',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            });
        });
    });

    const successAlert = document.querySelector('[data-swal-success]');

    if (successAlert && typeof Swal !== 'undefined') {
        Swal.fire({
            title: successAlert.dataset.swalTitle || 'สำเร็จ',
            text: successAlert.dataset.swalText || '',
            icon: 'success',
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#16406f',
        });
    }
});
