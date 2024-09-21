import './bootstrap';
import { createApp } from 'vue/dist/vue.esm-bundler';
/*
import App from './components/ChatList.vue';
const app = createApp(App);
app.component('App', App);
app.mount('#chat-list-vue');
*/
import Messenger from "./components/messages2/Messenger.vue";
import ChatList from "./components/messages2/ChatList.vue"
import Echo from "laravel-echo";
import Pusher from "pusher-js";

const chatApp = createApp({
    data() {
        return {
            conversations: [],
            conversation: null,
            messages: [],
            userId: userId,
            csrfToken: csrf_token,
            laravelEcho: null,
            chatChannel: null,
            users: [],
            alertAudio: new Audio('/assets/mixkit-correct-answer-tone-2870.wav'),
        }
    },
    mounted() {
        fetch('/api/conversations')
            .then(response => response.json()) // arrow function
            .then(json => {
                this.conversations = json.data;
            });

        this.laravelEcho = new Echo({
            broadcaster: 'pusher',
            key: import.meta.env.VITE_PUSHER_APP_KEY ? import.meta.env.VITE_PUSHER_APP_KEY : process.env.PUSHER_APP_KEY,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ? import.meta.env.VITE_PUSHER_APP_CLUSTER : process.env.PUSHER_APP_CLUSTER,
            forceTLS: true
        });

        this.laravelEcho
            .join(`Messenger.${this.userId}`) // join for presence
            .here((users) => {
                // alert('user connected');
            })
            .listen('.new-message', (data) => {
                let exists = false; // for new conversations

                for (let i in this.conversations) {
                    let conversation = this.conversations[i];
                    if (conversation.id === data.message.conversation_id) {
                        if (!conversation.hasOwnProperty('new_messages')) {
                            conversation.new_messages = 0;
                        }
                        conversation.new_messages++; // return from conversation query
                        conversation.last_message = data.message;
                        exists = true;
                        // this.conversations.splice(i, 1);
                        // this.conversations.unshift(conversation);

                        if (this.conversation && this.conversation.id == conversation.id) {
                            this.messages.push(data.message);
                            let container = document.querySelector('#chat-body');
                            if (container) { container.scroll({ top: container.scrollHeight, behavior: 'smooth'})}
                        }
                        break;
                    }
                }

                if (!exists) {
                fetch(`/api/conversations/${data.message.conversation_id}`)
                    .then(response => response.json())
                    .then(json => {
                        this.conversations.unshift(json)
                    })
                }

                this.alertAudio.play();

            });

        this.chatChannel = this.laravelEcho.join('Chat')
            .joining((user) => {
                for (let i in this.conversations) {
                    let conversation = this.conversations[i];
                    if (conversation.participants[0].id === user.id) {
                        this.conversations[i].participants[0].isOnline = true;
                        return;
                    }
                }
            })
            .leaving((user) => {
                for (let i in this.conversations) {
                    let conversation = this.conversations[i];
                    if (conversation.participants[0].id === user.id) {
                        this.conversations[i].participants[0].isOnline = false;
                        return;
                    }
                }
            }).listenForWhisper('typing', (e) => {
                let user = this.findUser(e.id, e.conversation_id);
                if (user) {
                    user.isTyping = true;
                }
            }).listenForWhisper('stopped-typing', (e) => {
                let user = this.findUser(e.id, e.conversation_id);
                if (user) {
                    user.isTyping = false;
                }
            });
    },
    methods: {
        isOnline(user) {
            for (let i in this.users) {
                if (this.users[i].id == user.id) {
                    return this.users[i].isOnline;
                }
            }
            return false;
        },
        findUser(id, conversation_id) {
            for (let i in this.conversations) {
                let conversation = this.conversations[i];
                if (conversation.id === conversation_id && conversation.participants[0].id == id) {
                    return this.conversations[i].participants[0];
                }
            }
        },
        markAsRead(conversation = null){
            if (conversation == null) {
                conversation = this.conversation;
            }
            fetch(`/api/conversations/${conversation.id}/read`, {
                method: 'PUT',
                mode: 'cors',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    _token: this.$root.csrfToken
                })
            })
                .then(response => response.json())
                .then(json => {
                    conversation.new_messages = 0;
                })
        },
        deleteMessage(message, target) {
            fetch(`/api/messages/${message.id}`, {
                method: 'DELETE',
                mode: 'cors',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    // 'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: JSON.stringify({
                    target: target,
                    _token: this.$root.csrfToken
                })
            })
                .then(response => response.json())
                .then(json => {
                    // let idx = this.messages.indexOf(message);
                    // this.messages.splice(idx, 1);
                    message.body = 'Message deleted..'
                })
        }

    }
})
        .component('Messenger', Messenger)
        .component('ChatList', ChatList)
        .mount('#chat-list-body');

