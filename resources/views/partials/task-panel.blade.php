{{-- Task detail slide-over --}}
<div x-data="taskPanel()" x-show="open" x-cloak>
    <div class="overlay" :class="{ show: open }" @click="close()"></div>

    <div class="panel" :class="{ show: open }">
        <div class="panel-head">
            <div>
                <div
                    class="mono"
                    style="color:var(--ink-faint); font-size:11px;"
                    x-text="'TASK-' + task.id"
                ></div>

                <h3
                    style="margin-top:4px; font-size:16px;"
                    x-text="task.name"
                ></h3>
            </div>

            <div class="panel-close" @click="close()">✕</div>
        </div>

        <div class="panel-body">

            {{-- STATUS --}}
            <div class="field-row">
                <span class="k">Status</span>

                <span class="v">
                    <template x-if="task.can_update_status">
                        <select
                            x-model="task.status"
                            @change="updateStatus()"
                            style="border:1px solid var(--line); border-radius:6px; padding:4px 8px; font-size:12.5px; font-family:inherit; font-weight:600; color:inherit; background:var(--surface);"
                        >
                            <template x-for="s in task.statuses" :key="s">
                                <option
                                    :value="s"
                                    x-text="s"
                                ></option>
                            </template>
                        </select>
                    </template>

                    <template x-if="!task.can_update_status">
                        <span x-text="task.status"></span>
                    </template>
                </span>
            </div>

            {{-- ASSIGNEE --}}
            <div class="field-row">
                <span class="k">Assignee</span>

                <span class="v">
                    <template x-if="task.can_manage">
                        <select
                            x-model="task.assignee_id"
                            @change="reassign()"
                            style="border:1px solid var(--line); border-radius:6px; padding:4px 8px; font-size:12.5px; font-family:inherit; font-weight:600; color:inherit; background:var(--surface);"
                        >
                            <template
                                x-for="u in task.assignable_users"
                                :key="u.id"
                            >
                                <option
                                    :value="u.id"
                                    x-text="u.name"
                                ></option>
                            </template>
                        </select>
                    </template>

                    <template x-if="!task.can_manage">
                        <span x-text="task.assignee"></span>
                    </template>
                </span>
            </div>

            {{-- OTHER INFORMATION --}}
            <div class="field-row">
                <span class="k">Priority</span>
                <span class="v" x-text="task.priority"></span>
            </div>

            <div class="field-row">
                <span class="k">Phase</span>
                <span class="v" x-text="task.phase"></span>
            </div>

            <div class="field-row">
                <span class="k">Due</span>
                <span class="v" x-text="task.due"></span>
            </div>

            {{-- SUCCESS MESSAGE --}}
            <div
                x-show="savedMessage"
                x-cloak
                style="margin-top:12px; font-size:12px; color:var(--success); font-weight:600;"
                x-text="savedMessage"
            ></div>

            {{-- DESCRIPTION --}}
            <div style="margin-top:18px;">
                <div
                    class="stat-label"
                    style="margin-bottom:8px;"
                >
                    Description
                </div>

                <div
                    style="font-size:13px; color:var(--ink-soft); line-height:1.6;"
                    x-text="task.description || 'No description provided.'"
                ></div>
            </div>

            {{-- SUBTASKS --}}
            <div
                style="margin-top:20px;"
                x-show="task.subtasks && task.subtasks.length"
            >
                <div
                    class="stat-label"
                    style="margin-bottom:8px;"
                >
                    Subtasks
                </div>

                <template
                    x-for="s in task.subtasks"
                    :key="s.name"
                >
                    <div class="list-row">
                        <span
                            x-text="(s.status === 'Done' ? '☑ ' : '☐ ') + s.name"
                        ></span>
                    </div>
                </template>
            </div>

            {{-- COMMENTS --}}
            <div style="margin-top:20px;">

                <div
                    class="stat-label"
                    style="margin-bottom:10px;"
                >
                    Activity &amp; comments
                </div>

                <template
                    x-for="c in task.comments"
                    :key="c.text + c.at"
                >
                    <div class="comment">

                        <div
                            class="avatar"
                            x-text="(c.user || '?')
                                .split(' ')
                                .map(w => w[0])
                                .join('')"
                        ></div>

                        <div class="txt">

                            {{-- FIXED: c is now used inside the x-for scope --}}
                            <div class="who">
                                <span x-text="c.user || 'Unknown user'"></span>

                                <span
                                    class="when"
                                    x-text="c.at || ''"
                                ></span>
                            </div>

                            <span x-text="c.text"></span>

                        </div>
                    </div>
                </template>

                <div
                    x-show="!task.comments || !task.comments.length"
                    style="font-size:12.5px; color:var(--ink-faint);"
                >
                    No comments yet.
                </div>

                {{-- NEW COMMENT --}}
                <div
                    style="display:flex; gap:8px; margin-top:12px;"
                >
                    <input
                        x-model="newComment"
                        @keydown.enter.prevent="postComment()"
                        placeholder="Write a comment…"
                        style="flex:1; border:1px solid var(--line); border-radius:8px; padding:9px 11px; font-size:12.8px; font-family:inherit;"
                    >

                    <button
                        class="btn btn-primary"
                        style="padding:9px 14px;"
                        @click="postComment()"
                        :disabled="posting"
                    >
                        <span x-text="posting ? 'Sending…' : 'Send'"></span>
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function taskPanel() {
    return {
        open: false,

        task: {
            comments: [],
            subtasks: [],
            statuses: [],
            assignable_users: []
        },

        dirty: false,
        newComment: '',
        posting: false,
        savedMessage: '',

        csrf() {
            const token = document.querySelector(
                'meta[name="csrf-token"]'
            );

            if (!token) {
                console.error(
                    'CSRF token meta tag is missing from the page.'
                );

                return '';
            }

            return token.getAttribute('content');
        },

        async show(taskId) {
            try {
                const res = await fetch(`/tasks/${taskId}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!res.ok) {
                    console.error(
                        'Failed to load task:',
                        res.status,
                        await res.text()
                    );

                    return;
                }

                this.task = await res.json();

                this.task.comments = this.task.comments || [];
                this.task.subtasks = this.task.subtasks || [];
                this.task.statuses = this.task.statuses || [];
                this.task.assignable_users =
                    this.task.assignable_users || [];

                this.open = true;
                this.dirty = false;

            } catch (error) {
                console.error('Error loading task:', error);
            }
        },

        close() {
            this.open = false;

            if (this.dirty) {
                window.location.reload();
            }
        },

        flash(message) {
            this.savedMessage = message;

            setTimeout(() => {
                this.savedMessage = '';
            }, 2500);
        },

        async updateStatus() {
            try {
                const token = this.csrf();

                if (!token) {
                    return;
                }

                const res = await fetch(
                    `/tasks/${this.task.id}/status`,
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token
                        },

                        body: JSON.stringify({
                            status: this.task.status
                        })
                    }
                );

                if (!res.ok) {
                    console.error(
                        'Failed to update status:',
                        res.status,
                        await res.text()
                    );

                    return;
                }

                const data = await res.json();

                this.task.status = data.status;

                this.flash('Status updated');

                this.dirty = true;

            } catch (error) {
                console.error(
                    'Status update error:',
                    error
                );
            }
        },

        async reassign() {
            try {
                const token = this.csrf();

                if (!token) {
                    return;
                }

                const res = await fetch(
                    `/tasks/${this.task.id}/assign`,
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token
                        },

                        body: JSON.stringify({
                            assigned_to: this.task.assignee_id
                        })
                    }
                );

                if (!res.ok) {
                    console.error(
                        'Failed to reassign task:',
                        res.status,
                        await res.text()
                    );

                    return;
                }

                const data = await res.json();

                this.task.assignee_id = data.assignee_id;

                this.flash('Reassigned');

                this.dirty = true;

            } catch (error) {
                console.error(
                    'Reassignment error:',
                    error
                );
            }
        },

        async postComment() {
            const text = this.newComment.trim();

            if (!text || this.posting) {
                return;
            }

            const token = this.csrf();

            if (!token) {
                return;
            }

            this.posting = true;

            try {
                const res = await fetch(
                    `/tasks/${this.task.id}/comments`,
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token
                        },

                        body: JSON.stringify({
                            comment_text: text
                        })
                    }
                );

                if (!res.ok) {
                    const errorText = await res.text();

                    console.error(
                        'Failed to post comment:',
                        res.status,
                        errorText
                    );

                    this.flash(
                        'Could not send comment.'
                    );

                    return;
                }

                const comment = await res.json();

                this.task.comments = [
                    ...(this.task.comments || []),
                    comment
                ];

                this.newComment = '';

                this.flash('Comment added');

            } catch (error) {
                console.error(
                    'Comment error:',
                    error
                );

                this.flash(
                    'Could not send comment.'
                );

            } finally {
                this.posting = false;
            }
        }
    };
}

/*
 * Alpine v3-compatible global bridge.
 *
 * Do NOT use:
 *     element.__x.$data
 *
 * That was an Alpine v2 internal API.
 */
window.openTask = (id) => {
    const panel = document.querySelector(
        '[x-data^="taskPanel"]'
    );

    if (!panel) {
        console.error(
            'Task panel element was not found.'
        );

        return;
    }

    if (!window.Alpine) {
        console.error(
            'Alpine.js has not loaded yet.'
        );

        return;
    }

    Alpine.$data(panel).show(id);
};
</script>