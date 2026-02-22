import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const interactiveSection = document.getElementById('interactive-section');
    if (!interactiveSection) return;

    const postId = interactiveSection.dataset.postId;
    initLikes(postId);
    initComments(postId);
});

async function initLikes(postId) {
    const container = document.getElementById('like-button-container');
    if (!container) return;

    // Fetch current likes count (we could also have this in the initial page load data)
    // For simplicity, let's assume we want to show a "Like" button and the count.

    const renderLikeButton = (count, hasLiked = false) => {
        container.innerHTML = `
            <button id="like-btn" class="flex items-center gap-2 px-4 py-2 rounded-full border ${hasLiked ? 'bg-primary-50 border-primary-200 text-primary-600' : 'bg-white border-neutral-200 text-neutral-600'} hover:border-primary-300 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ${hasLiked ? 'fill-current' : 'fill-none'}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
                <span class="font-bold">${count}</span>
            </button>
        `;

        document.getElementById('like-btn').addEventListener('click', async () => {
            try {
                const response = await fetch(`/api/posts/${postId}/like`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    }
                });
                const data = await response.json();
                if (response.ok) {
                    renderLikeButton(data.likes_count, true);
                } else {
                    alert(data.message || 'Something went wrong');
                }
            } catch (e) {
                console.error('Like failed', e);
            }
        });
    };

    // We don't have a direct "get likes count" endpoint, but we can get it from the post API or just rely on the first click.
    // Let's assume we pass the initial count via data attributes.
    const initialCount = interactiveSection.dataset.likesCount || 0;
    renderLikeButton(initialCount);
}

async function initComments(postId) {
    const container = document.getElementById('comments-container');
    if (!container) return;

    const loadComments = async () => {
        const response = await fetch(`/api/posts/${postId}/comments`);
        const { data: comments } = await response.json();

        let html = `
            <h3 class="text-2xl font-bold mb-8">Comments (${comments.length})</h3>
            <div class="space-y-8 mb-12">
        `;

        if (comments.length === 0) {
            html += `<p class="text-neutral-500 italic">No comments yet. Be the first to share your thoughts!</p>`;
        } else {
            comments.forEach(comment => {
                html += `
                    <div class="bg-neutral-50 rounded-xl p-6 border border-neutral-100">
                        <div class="flex items-center justify-between mb-4">
                            <span class="font-bold text-neutral-900">${comment.author}</span>
                            <span class="text-xs text-neutral-400">${comment.created_at}</span>
                        </div>
                        <p class="text-neutral-600 leading-relaxed">${comment.comment}</p>
                    </div>
                `;
            });
        }

        html += `
            </div>
            <div class="bg-white rounded-2xl p-8 border border-neutral-100 shadow-sm">
                <h4 class="text-lg font-bold mb-6">Leave a comment</h4>
                <form id="comment-form" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Name</label>
                        <input type="text" name="author_name" required class="w-full px-4 py-2 rounded-lg border border-neutral-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Comment</label>
                        <textarea name="comment" required rows="4" class="w-full px-4 py-2 rounded-lg border border-neutral-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all"></textarea>
                    </div>
                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                        Post Comment
                    </button>
                </form>
            </div>
        `;

        container.innerHTML = html;

        document.getElementById('comment-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = {
                post_id: postId,
                author_name: formData.get('author_name'),
                comment: formData.get('comment')
            };

            try {
                const response = await fetch('/api/comments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    },
                    body: JSON.stringify(data)
                });

                if (response.ok) {
                    alert('Comment submitted! It will appear after moderation.');
                    e.target.reset();
                } else {
                    const errorData = await response.json();
                    alert(errorData.message || 'Validation failed');
                }
            } catch (error) {
                console.error('Submission failed', error);
            }
        });
    };

    loadComments();
}
