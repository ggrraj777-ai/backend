importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

firebase.initializeApp({
    apiKey: "AIzaSyCOMHyeFQUzMTODEpni1czDodOqEggSklE",
    authDomain: "gauva-15d9a.firebaseapp.com",
    projectId: "gauva-15d9a",
    storageBucket: "gauva-15d9a.firebasestorage.app",
    messagingSenderId: "798219755346",
    appId: "1:798219755346:web:1eec6b92d3a676239f648b",
    measurementId: "G-3HHEV0GX70"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function (payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body ? payload.data.body : '',
        icon: payload.data.icon ? payload.data.icon : ''
    });
});