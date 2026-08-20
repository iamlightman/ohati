// js/review.js — Production Verified Review Module

window.submitVendorReview = function(vendorId, rating, comment) {
    if (!vendorId || !comment || comment.trim().length < 5) {
        showPushNotification('Review Error', 'Please write a review comment (minimum 5 characters).');
        return Promise.reject(new Error('Review comment too short'));
    }

    const userName = state.user ? state.user.name : 'Verified Customer';

    return API.post('review', {
        vendor_id: vendorId,
        user_name: userName,
        rating: rating || 5,
        comment: comment.trim()
    })
    .then(res => {
        showPushNotification('Review Submitted', 'Thank you for reviewing this vendor!');
        if (typeof closeModal === 'function') closeModal();
        return res;
    })
    .catch(err => {
        showPushNotification('Error', err.message || 'Failed to submit review.');
        throw err;
    });
};

window.openWriteReviewModal = function(vendorId, vendorName) {
    const html = `
        <div style="max-width:400px; margin:0 auto; text-align:center;">
            <h3 style="margin-bottom:4px; font-weight:700;">Rate & Review</h3>
            <p style="font-size:0.75rem; color:var(--gray-500); margin-bottom:16px;">${vendorName || 'Vendor Service'}</p>
            
            <div id="star-rating-picker" style="display:flex; justify-content:center; gap:8px; font-size:1.8rem; color:var(--gray-300); margin-bottom:16px; cursor:pointer;">
                <i class="fa-solid fa-star star-opt" data-val="1" onclick="setReviewRating(1)"></i>
                <i class="fa-solid fa-star star-opt" data-val="2" onclick="setReviewRating(2)"></i>
                <i class="fa-solid fa-star star-opt" data-val="3" onclick="setReviewRating(3)"></i>
                <i class="fa-solid fa-star star-opt" data-val="4" onclick="setReviewRating(4)"></i>
                <i class="fa-solid fa-star star-opt" data-val="5" onclick="setReviewRating(5)"></i>
            </div>

            <textarea id="review-comment-input" class="form-textarea" placeholder="Share your experience working with this vendor..." style="height:90px; margin-bottom:14px; font-size:0.82rem;"></textarea>

            <div style="display:flex; gap:8px;">
                <button class="btn btn-outline btn-full" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary btn-full" id="btn-submit-review" onclick="handleReviewFormSubmit(${vendorId})">Submit Review</button>
            </div>
        </div>
    `;
    openModal(html);
    window._selectedRating = 5;
    setReviewRating(5);
};

window.setReviewRating = function(val) {
    window._selectedRating = val;
    const stars = document.querySelectorAll('#star-rating-picker .star-opt');
    stars.forEach((star, idx) => {
        if (idx < val) {
            star.style.color = '#F59E0B';
        } else {
            star.style.color = 'var(--gray-300)';
        }
    });
};

window.handleReviewFormSubmit = function(vendorId) {
    const comment = document.getElementById('review-comment-input')?.value || '';
    const rating = window._selectedRating || 5;
    const btn = document.getElementById('btn-submit-review');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Submitting...';
    }

    window.submitVendorReview(vendorId, rating, comment)
        .catch(() => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Submit Review';
            }
        });
};
