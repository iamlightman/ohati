// reels_data.js — Isolated Demonstration Data for OHATI Reels (Using authentic local Chill & Serve images & videos)
(function() {
    'use strict';

    const INITIAL_REELS = [
        {
            id: 'reel_1',
            creator: {
                id: 'creator_1',
                name: 'Chill & Serve Events',
                username: '@chill_serve_gh',
                avatar: '../img/chill/logo.jpg',
                verified: true,
                bio: 'Premium event bar, cocktail catering & beverage logistics across Ghana 🍸✨ Booking open on Ohati!',
                followers: '28.4K',
                following: '180',
                totalLikes: '242K'
            },
            caption: 'Exclusive mobile bar setup for today\'s luxury wedding reception in East Legon! 🍸✨ Serving signature tropical cocktails!',
            hashtags: '#ChillAndServe #GhanaWeddings #OhatiReels #EastLegonEvents',
            location: 'East Legon, Accra',
            videoUrl: '../img/chill/v1_opt.mp4',
            coverUrl: '../img/chill/event1.jpg',
            likes: 1840,
            commentsCount: 92,
            reposts: 45,
            views: 15200,
            soundTrack: 'Original Audio - Chill & Serve • Cocktail Highlife Mix',
            createdAt: '1 hour ago',
            comments: [
                { id: 'c1', user: 'Ama Serwaa', avatar: '../img/chill/1.jpg', text: 'Best cocktail service in Accra! The presentation is top tier 🔥🍹', time: '45m ago', likes: 18 },
                { id: 'c2', user: 'Kofi Arhin', avatar: '../img/chill/2.jpg', text: 'Booking them for my brother\'s wedding in December!', time: '20m ago', likes: 6 }
            ]
        },
        {
            id: 'reel_2',
            creator: {
                id: 'creator_2',
                name: 'Chill & Serve Cocktails',
                username: '@chill_serve_gh',
                avatar: '../img/chill/logo.jpg',
                verified: true,
                bio: 'Custom cocktail menus, mixology stations & bar logistics 🍹🍸',
                followers: '28.4K',
                following: '180',
                totalLikes: '242K'
            },
            caption: 'Unforgettable traditional Ghanaian wedding bar entrance in Kumasi! 🇬🇭✨ Refreshing drinks flowing for all guests!',
            hashtags: '#ChillAndServe #GhanaWeddings #KenteBridal #KumasiEvents',
            location: 'Kumasi, Ghana',
            videoUrl: '../img/chill/v2_opt.mp4',
            coverUrl: '../img/chill/event2.jpg',
            likes: 2890,
            commentsCount: 154,
            reposts: 82,
            views: 24100,
            soundTrack: 'Original Audio - Chill & Serve • Highlife Vibe',
            createdAt: '3 hours ago',
            comments: [
                { id: 'c3', user: 'Nana Yaa', avatar: '../img/chill/3.jpg', text: 'The drink setup and service is perfection! 😍', time: '2h ago', likes: 25 }
            ]
        },
        {
            id: 'reel_3',
            creator: {
                id: 'creator_3',
                name: 'Chill & Serve Luxury Bar',
                username: '@chill_serve_gh',
                avatar: '../img/chill/logo.jpg',
                verified: true,
                bio: 'Luxury mobile bar installations for weddings & corporate galas 🍷🥂',
                followers: '28.4K',
                following: '180',
                totalLikes: '242K'
            },
            caption: 'Transforming Labadi Beach Hotel ballroom for a 500-guest luxury reception ✨ Crystal bar counters & premium spirits!',
            hashtags: '#ChillAndServe #LuxuryWeddings #LabadiEvents #OhatiPlanner',
            location: 'Labadi Beach Hotel, Accra',
            videoUrl: '../img/chill/v3_opt.mp4',
            coverUrl: '../img/chill/event3.jpg',
            likes: 4120,
            commentsCount: 210,
            reposts: 145,
            views: 45800,
            soundTrack: 'Original Audio - Chill & Serve • Ambient Elegance',
            createdAt: '5 hours ago',
            comments: [
                { id: 'c4', user: 'Dr. Selorm', avatar: '../img/chill/4.jpg', text: 'Breathtaking bar setup! Highly professional team.', time: '3h ago', likes: 42 }
            ]
        },
        {
            id: 'reel_4',
            creator: {
                id: 'creator_4',
                name: 'Chill & Serve Party Zone',
                username: '@chill_serve_gh',
                avatar: '../img/chill/logo.jpg',
                verified: true,
                bio: 'Event beverage management & high-energy festival bars 🎧🍹',
                followers: '28.4K',
                following: '180',
                totalLikes: '242K'
            },
            caption: 'When the wedding reception turns into an absolute festival in Cantonments! 🎧🔥 Shots & cocktails pouring non-stop!',
            hashtags: '#ChillAndServe #WeddingPartyGH #OhatiReels #CantonmentsNight',
            location: 'Cantonments, Accra',
            videoUrl: '../img/chill/v4_opt.mp4',
            coverUrl: '../img/chill/event4.jpg',
            likes: 6300,
            commentsCount: 432,
            reposts: 310,
            views: 89200,
            soundTrack: 'Live Set - Chill & Serve • Ghana Jamz 2026',
            createdAt: '1 day ago',
            comments: [
                { id: 'c5', user: 'Papa Yaw', avatar: '../img/chill/5.jpg', text: 'Best bar team in West Africa no cap! 🎧🔥', time: '18h ago', likes: 88 }
            ]
        },
        {
            id: 'reel_5',
            creator: {
                id: 'creator_5',
                name: 'Chill & Serve Bartending',
                username: '@chill_serve_gh',
                avatar: '../img/chill/logo.jpg',
                verified: true,
                bio: 'Premium event bar, cocktail catering & beverage logistics across Ghana 🍸✨',
                followers: '28.4K',
                following: '180',
                totalLikes: '242K'
            },
            caption: 'Crafting signature handcrafted mocktails & cocktails live at Peduase Valley Resort! 🍹🍃 Fresh ingredients only!',
            hashtags: '#ChillAndServe #MixologyGhana #OhatiEvents #PeduaseResort',
            location: 'Peduase Valley Resort, Aburi',
            videoUrl: '../img/chill/v5_opt.mp4',
            coverUrl: '../img/chill/event5.jpg',
            likes: 2450,
            commentsCount: 112,
            reposts: 68,
            views: 21800,
            soundTrack: 'Original Audio - Chill & Serve • Sunset Lounge',
            createdAt: '2 days ago',
            comments: [
                { id: 'c6', user: 'Yaw Boateng', avatar: '../img/chill/6.jpg', text: 'Tasted these cocktails at the corporate gala, 10/10!!', time: '1d ago', likes: 23 }
            ]
        },
        {
            id: 'reel_6',
            creator: {
                id: 'creator_6',
                name: 'Chill & Serve Mixology',
                username: '@chill_serve_gh',
                avatar: '../img/chill/logo.jpg',
                verified: true,
                bio: 'Artisanal cocktails & bespoke drink experiences for private events 🍹',
                followers: '28.4K',
                following: '180',
                totalLikes: '242K'
            },
            caption: 'Behind the scenes: Preparing fresh garnishes & ice carousels for today\'s traditional engagement! 👑💍',
            hashtags: '#ChillAndServe #GhanaWeddings #OhatiReels #BarPrep',
            location: 'Airport Residential, Accra',
            videoUrl: '../img/chill/v6_opt.mp4',
            coverUrl: '../img/chill/event6.jpg',
            likes: 3120,
            commentsCount: 178,
            reposts: 94,
            views: 31500,
            soundTrack: 'Original Audio - Chill & Serve • Afrobeats',
            createdAt: '2 days ago',
            comments: [
                { id: 'c7', user: 'Akosua K.', avatar: '../img/chill/services.jpg', text: 'Stunning service! Ohati makes booking so easy.', time: '1d ago', likes: 14 }
            ]
        },
        {
            id: 'reel_7',
            creator: {
                id: 'creator_7',
                name: 'Chill & Serve VIP Service',
                username: '@chill_serve_gh',
                avatar: '../img/chill/logo.jpg',
                verified: true,
                bio: 'Premium event bar, cocktail catering & beverage logistics across Ghana 🍸✨',
                followers: '28.4K',
                following: '180',
                totalLikes: '242K'
            },
            caption: 'VIP champagne & cocktail service setup for 400 guests! 🥂✨ Seamless beverage service for your big day.',
            hashtags: '#ChillAndServe #VIPBarGhana #OhatiVendors #AccraEvents',
            location: 'Kempinski Hotel, Accra',
            videoUrl: '../img/chill/v7_opt.mp4',
            coverUrl: '../img/chill/event7.jpg',
            likes: 4890,
            commentsCount: 245,
            reposts: 180,
            views: 52400,
            soundTrack: 'Original Audio - Chill & Serve • VIP Celebration Mix',
            createdAt: '3 days ago',
            comments: [
                { id: 'c8', user: 'Efya Gold', avatar: '../img/chill/logo.jpg', text: 'Pure luxury! The bar team was super professional. ❤️', time: '2d ago', likes: 31 }
            ]
        }
    ];

    // Local Isolated Storage Helper (Reads/Writes ONLY to demo localStorage)
    const STORAGE_KEY = 'ohati_reels_demo_state_v1';

    function loadState() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                const parsed = JSON.parse(stored);
                return parsed;
            }
        } catch (e) {
            console.warn('Could not read demo reels state from localStorage:', e);
        }
        return {
            likedReels: {}, // reelId -> boolean
            likeCounts: {}, // reelId -> number
            repostedReels: {}, // reelId -> boolean
            repostCounts: {}, // reelId -> number
            followedCreators: {}, // creatorId -> boolean
            comments: {} // reelId -> Array of comment objects
        };
    }

    function saveState(state) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            console.warn('Could not save demo reels state to localStorage:', e);
        }
    }

    // Export to global scope for demo
    window.OhatiReelsData = {
        reels: INITIAL_REELS,
        loadState: loadState,
        saveState: saveState
    };
})();
