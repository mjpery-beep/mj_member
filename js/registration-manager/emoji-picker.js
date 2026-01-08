/**
 * Registration Manager - Shared Emoji Picker
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;

    if (!preact || !hooks || !Utils) {
        console.warn('[MjRegMgr] Missing dependencies for emoji-picker.js');
        return;
    }

    var h = preact.h;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useMemo = hooks.useMemo;
    var useCallback = hooks.useCallback;
    var useRef = hooks.useRef;

    var classNames = typeof Utils.classNames === 'function'
        ? Utils.classNames
        : function (base, modifiers) {
            var classes = base ? [base] : [];
            if (!modifiers) {
                return classes.join(' ');
            }
            Object.keys(modifiers).forEach(function (key) {
                if (modifiers[key]) {
                    classes.push(key);
                }
            });
            return classes.join(' ');
        };

    var rawGetString = typeof Utils.getString === 'function' ? Utils.getString : null;

    function getString(strings, key, fallback) {
        if (rawGetString) {
            return rawGetString(strings, key, fallback);
        }
        if (strings && typeof strings[key] === 'string') {
            return strings[key];
        }
        return fallback;
    }

    function sliceGraphemes(text, max) {
        if (typeof text !== 'string' || !max || max <= 0) {
            return '';
        }
        if (typeof Intl !== 'undefined' && typeof Intl.Segmenter === 'function') {
            try {
                var segmenter = new Intl.Segmenter(undefined, { granularity: 'grapheme' });
                var iterator = segmenter.segment(text);
                var collected = '';
                var count = 0;
                if (iterator && typeof Symbol === 'function' && typeof iterator[Symbol.iterator] === 'function') {
                    var iter = iterator[Symbol.iterator]();
                    var step = iter.next();
                    while (!step.done && count < max) {
                        collected += step.value.segment;
                        count++;
                        step = iter.next();
                    }
                    return collected;
                }
            } catch (segmenterError) {
                // ignore segmenter issues and fall back to code point slicing
            }
        }
        var units;
        try {
            units = Array.from(text);
        } catch (arrayError) {
            units = String(text).split('');
        }
        return units.slice(0, max).join('');
    }

    function sanitizeEmojiValue(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var normalized = value.replace(/\s+/g, ' ').trim();
        if (normalized === '') {
            return '';
        }
        var limited = sliceGraphemes(normalized, 8);
        if (limited.length > 16) {
            limited = limited.slice(0, 16);
        }
        return limited;
    }

    function normalizeEmojiSearchValue(value) {
        if (!value) {
            return '';
        }
        var text = String(value).toLowerCase();
        if (typeof text.normalize === 'function') {
            text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return text.replace(/[^a-z0-9\s]/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function createEmojiHelper(definition) {
        var categories = [];
        var flat = [];

        if (Array.isArray(definition)) {
            definition.forEach(function (categoryDef, categoryIndex) {
                if (!categoryDef) {
                    return;
                }

                var key = categoryDef.key ? String(categoryDef.key) : 'category-' + categoryIndex;
                var label = categoryDef.label ? String(categoryDef.label) : key;
                var rawItems = Array.isArray(categoryDef.items) ? categoryDef.items : [];

                var items = rawItems.map(function (rawItem, itemIndex) {
                    var symbol = '';
                    var name = '';
                    var keywords = [];

                    if (typeof rawItem === 'string') {
                        symbol = rawItem;
                    } else if (rawItem && typeof rawItem === 'object') {
                        symbol = rawItem.symbol || '';
                        if (rawItem.name) {
                            name = String(rawItem.name);
                        }
                        if (Array.isArray(rawItem.keywords)) {
                            keywords = rawItem.keywords.map(String);
                        } else if (rawItem.keywords) {
                            keywords = [String(rawItem.keywords)];
                        }
                    }

                    symbol = sanitizeEmojiValue(symbol);
                    if (!symbol) {
                        return null;
                    }

                    var searchParts = [symbol, name].concat(keywords);
                    var searchIndex = searchParts.map(normalizeEmojiSearchValue).filter(Boolean).join(' ');

                    return {
                        symbol: symbol,
                        name: name,
                        keywords: keywords,
                        search: searchIndex,
                        category: key,
                        categoryLabel: label,
                    };
                }).filter(Boolean);

                if (!items.length) {
                    return;
                }

                var category = {
                    key: key,
                    label: label,
                    items: items,
                };

                categories.push(category);
                flat = flat.concat(items);
            });
        }

        return {
            getCategories: function () {
                return categories.slice();
            },
            listAll: function () {
                return flat.slice();
            },
            filter: function (options) {
                var query = options && options.query ? normalizeEmojiSearchValue(options.query) : '';
                var categoryKey = options && options.category ? String(options.category) : null;
                var target = categoryKey
                    ? categories.filter(function (category) { return category.key === categoryKey; })
                    : categories;

                return target.map(function (category) {
                    var items = category.items.filter(function (item) {
                        if (!query) {
                            return true;
                        }
                        return item.search.indexOf(query) !== -1;
                    });

                    return {
                        key: category.key,
                        label: category.label,
                        items: items,
                    };
                });
            },
        };
    }

    function parseEmojiBlock(block) {
        if (typeof block !== 'string') {
            return [];
        }
        return block.split('\n').map(function (line) {
            var trimmed = line.trim();
            if (!trimmed || trimmed.charAt(0) === '#') {
                return null;
            }
            var parts = trimmed.split('|');
            var symbol = parts[0] ? parts[0].trim() : '';
            if (!symbol) {
                return null;
            }
            var name = parts[1] ? parts[1].trim() : '';
            var keywords = [];
            if (parts.length > 2) {
                keywords = parts[2].split(',').map(function (part) {
                    return part.trim();
                }).filter(Boolean);
            }
            return {
                symbol: symbol,
                name: name,
                keywords: keywords,
            };
        }).filter(Boolean);
    }

    function buildFlagEntries(records) {
        if (!Array.isArray(records)) {
            return [];
        }
        var displayNames = null;
        if (typeof Intl !== 'undefined' && typeof Intl.DisplayNames === 'function') {
            try {
                displayNames = new Intl.DisplayNames(['fr', 'en'], { type: 'region' });
            } catch (displayNameError) {
                displayNames = null;
            }
        }

        return records.map(function (entry) {
            var code = '';
            var label = '';
            var supplementalKeywords = [];

            if (typeof entry === 'string') {
                var parts = entry.split('|');
                code = (parts[0] || '').trim().toUpperCase();
                if (parts.length > 1) {
                    label = (parts[1] || '').trim();
                }
                if (parts.length > 2) {
                    supplementalKeywords = parts[2].split(',').map(function (part) {
                        return part.trim();
                    }).filter(Boolean);
                }
            } else if (entry && typeof entry === 'object') {
                code = entry.code ? String(entry.code).trim().toUpperCase() : '';
                label = entry.name ? String(entry.name).trim() : '';
                if (Array.isArray(entry.keywords)) {
                    supplementalKeywords = entry.keywords.map(function (keyword) {
                        return String(keyword).trim();
                    }).filter(Boolean);
                }
            }

            if (!code || code.length !== 2) {
                return null;
            }

            var base = 0x1F1E6;
            var first = code.charCodeAt(0);
            var second = code.charCodeAt(1);
            if (first < 65 || first > 90 || second < 65 || second > 90) {
                return null;
            }

            var symbol = String.fromCodePoint(base + (first - 65)) + String.fromCodePoint(base + (second - 65));
            var resolvedLabel = label;
            if (!resolvedLabel && displayNames) {
                try {
                    resolvedLabel = displayNames.of(code) || '';
                } catch (nameError) {
                    resolvedLabel = '';
                }
            }
            if (!resolvedLabel) {
                resolvedLabel = code;
            }

            var keywords = ['drapeau', 'flag', code.toLowerCase()];
            var asciiLabel = normalizeEmojiSearchValue(resolvedLabel);
            if (asciiLabel) {
                asciiLabel.split(' ').forEach(function (part) {
                    if (part && keywords.indexOf(part) === -1) {
                        keywords.push(part);
                    }
                });
            }

            supplementalKeywords.forEach(function (keyword) {
                var value = normalizeEmojiSearchValue(keyword);
                if (!value) {
                    return;
                }
                value.split(' ').forEach(function (chunk) {
                    if (chunk && keywords.indexOf(chunk) === -1) {
                        keywords.push(chunk);
                    }
                });
            });

            return {
                symbol: symbol,
                name: resolvedLabel,
                keywords: keywords,
            };
        }).filter(Boolean);
    }

    var DEFAULT_EMOJI_LIBRARY = (function () {
        var categories = [
            {
                key: 'smileys',
                label: 'Smileys & Emotion',
                block: [
                    "😀|Grinning Face|smile,joie,heureux",
                    "😃|Grinning Face With Big Eyes|smile,joie,enthousiasme",
                    "😄|Grinning Face With Smiling Eyes|smile,joie,beam",
                    "😁|Beaming Face With Smiling Eyes|sourire,heureux,yeux",
                    "😆|Grinning Squinting Face|rire,joie,hilarant",
                    "😅|Grinning Face With Sweat|soulagement,rire,sueur",
                    "😂|Face With Tears Of Joy|rire,joie,mdr",
                    "🤣|Rolling On The Floor Laughing|rire,mdr,folie",
                    "😊|Smiling Face With Smiling Eyes|smile,doux,heureux",
                    "😇|Smiling Face With Halo|ange,gentil,innocent",
                    "🙂|Slightly Smiling Face|smile,leger,cordial",
                    "🙃|Upside Down Face|ironie,humour,retourne",
                    "😉|Winking Face|clin,complice,humour",
                    "😌|Relieved Face|soulagement,calme,zen",
                    "😍|Smiling Face With Hearts|amour,coeur,admirer",
                    "🥰|Smiling Face With Hearts|coeur,amour,tendre",
                    "😘|Face Blowing A Kiss|baiser,coeur,amour",
                    "😗|Kissing Face|baiser,tendre,doux",
                    "😙|Kissing Face With Smiling Eyes|baiser,sourire,tendre",
                    "😚|Kissing Face With Closed Eyes|baiser,affection,doux",
                    "😋|Face Savoring Food|delicieux,gourmand,yummy",
                    "😛|Face With Tongue|blague,fun,grimace",
                    "😜|Winking Face With Tongue|taquin,fun,grimace",
                    "😝|Squinting Face With Tongue|grimace,folie,rire",
                    "🤑|Money Mouth Face|argent,gain,business",
                    "🤗|Smiling Face With Open Hands|calin,accueil,merci",
                    "🤭|Face With Hand Over Mouth|surprise,secret,oh",
                    "🤫|Shushing Face|silence,secret,chut",
                    "🤔|Thinking Face|idee,reflexion,question",
                    "🤨|Face With Raised Eyebrow|sceptique,doute,question",
                    "🧐|Face With Monocle|analyse,inspecter,serieux",
                    "🤓|Nerd Face|geek,lecture,smart",
                    "😎|Smiling Face With Sunglasses|cool,detente,style",
                    "🤩|Star Struck|admiration,etoiles,fan",
                    "🥳|Partying Face|fete,anniversaire,joie",
                    "😏|Smirking Face|satisfait,malice,complice",
                    "😒|Unamused Face|bof,blase,doute",
                    "😞|Disappointed Face|decu,triste,baisse",
                    "😔|Pensive Face|pensif,triste,reflexion",
                    "😟|Worried Face|inquiet,stress,peur",
                    "😕|Confused Face|confus,perdu,question",
                    "🙁|Slightly Frowning Face|triste,mecontent,leger",
                    "☹️|Frowning Face|triste,decu,negatif",
                    "😣|Persevering Face|stress,tension,effort",
                    "😖|Confounded Face|frustration,trouble,stress",
                    "😫|Tired Face|fatigue,epuise,souffle",
                    "😩|Weary Face|fatigue,sature,stress",
                    "🥺|Pleading Face|supplication,silvousplait,coeur",
                    "😢|Crying Face|pleurer,triste,chagrin",
                    "😭|Loudly Crying Face|pleure,fort,triste",
                    "😤|Face With Steam From Nose|determination,colere,effort",
                    "😠|Angry Face|colere,rouge,furieux",
                    "😡|Pouting Face|furieux,colere,gronder",
                    "🤬|Face With Symbols On Mouth|injure,furieux,colere",
                    "🤯|Exploding Head|mindblown,idee,shock",
                    "😳|Flushed Face|gene,surpris,rougir",
                    "🥵|Hot Face|chaleur,coupchaud,ete",
                    "🥶|Cold Face|froid,hiver,glacial",
                    "😱|Face Screaming In Fear|cri,peur,horreur",
                    "😨|Fearful Face|peur,inquiet,crainte",
                    "😰|Anxious Face With Sweat|stress,peur,sueur",
                    "😥|Sad But Relieved Face|soulagement,triste,pleurs",
                    "😓|Downcast Face With Sweat|stress,travail,fatigue",
                    "🤤|Drooling Face|envie,gourmand,desir",
                    "😴|Sleeping Face|sommeil,dodo,fatigue",
                    "😪|Sleepy Face|sommeil,fatigue,baille",
                    "😮|Face With Open Mouth|surprise,choque,ouvert",
                    "😯|Hushed Face|surpris,calme,silence",
                    "😲|Astonished Face|surpris,shock,etonne",
                    "😵|Dizzy Face|vertige,etourdi,tourne",
                    "😵‍💫|Face With Spiral Eyes|vertige,hypnose,etonne",
                    "🤐|Zipper Mouth Face|secret,silence,chut",
                    "🥴|Woozy Face|etourdi,alcool,fatigue",
                    "🤢|Nauseated Face|degout,malade,poison",
                    "🤮|Face Vomiting|malade,gastro,degout",
                    "🤧|Sneezing Face|rhume,allergie,malade",
                    "😷|Face With Medical Mask|masque,malade,sante",
                    "🤒|Face With Thermometer|fievre,malade,sante",
                    "🤕|Face With Head-Bandage|blessure,accident,sante",
                    "🫠|Melting Face|chaleur,gene,fondre",
                    "🫢|Face With Open Eyes And Hand Over Mouth|surpris,secret,shock",
                    "🫣|Face With Peeking Eye|curieux,timide,peur",
                    "🫡|Saluting Face|respect,salut,serieux",
                    "🫥|Dotted Line Face|invisible,timidite,silence",
                    "🫤|Face With Diagonal Mouth|incertain,doute,meh",
                    "😶|Face Without Mouth|silence,mute,secret",
                    "😶‍🌫️|Face In Clouds|reve,flou,meteo",
                    "😐|Neutral Face|neutre,calme,plat",
                    "😑|Expressionless Face|neutre,plat,silence",
                    "😬|Grimacing Face|malais,stress,awkward",
                    "🫨|Shaking Face|tremble,secousse,shock",
                    "🤠|Cowboy Hat Face|western,fun,joie",
                    "😈|Smiling Face With Horns|diable,fete,malice",
                    "👿|Angry Face With Horns|demon,colere,mechant",
                    "👹|Ogre|oni,japon,monstre",
                    "👺|Goblin|tengu,masque,monstre",
                    "💀|Skull|pirate,halloween,danger",
                    "☠️|Skull And Crossbones|danger,toxique,poison",
                    "👻|Ghost|halloween,esprit,boo",
                    "👽|Alien|ovni,extra,space",
                    "👾|Alien Monster|retro,jeu,arcade",
                    "🤖|Robot|tech,futur,bot",
                    "😺|Grinning Cat Face|chat,smile,joie",
                    "😸|Grinning Cat With Smiling Eyes|chat,joie,sourire",
                    "😹|Cat With Tears Of Joy|chat,rire,joie",
                    "😻|Smiling Cat With Heart Eyes|chat,amour,adorable",
                    "😼|Cat With Wry Smile|chat,malicieux,coquin",
                    "😽|Kissing Cat|chat,bisou,affection",
                    "🙀|Weary Cat|chat,shock,peur",
                    "😿|Crying Cat|chat,triste,pleur",
                    "😾|Pouting Cat|chat,colere,mecontent",
                    "💩|Pile Of Poo|blague,humour,mdr",
                    "❤️|Red Heart|coeur,amour,passion",
                    "🩷|Pink Heart|coeur,rose,affection",
                    "🧡|Orange Heart|coeur,amitie,gratitude",
                    "💛|Yellow Heart|coeur,soleil,amitie",
                    "💚|Green Heart|coeur,nature,espoir",
                    "💙|Blue Heart|coeur,confiance,paix",
                    "💜|Purple Heart|coeur,solidarite,creativite",
                    "🖤|Black Heart|coeur,style,goth",
                    "🤍|White Heart|coeur,pur,paix",
                    "🤎|Brown Heart|coeur,chaleur,terre",
                    "💔|Broken Heart|rupture,triste,amour",
                    "❣️|Heart Exclamation|coeur,attention,amour",
                    "💕|Two Hearts|coeur,amour,affection",
                    "💞|Revolving Hearts|coeur,romance,douceur",
                    "💓|Beating Heart|coeur,rythme,amour",
                    "💗|Growing Heart|coeur,progression,joie",
                    "💖|Sparkling Heart|coeur,etincelle,magie",
                    "💘|Heart With Arrow|amour,cupidon,valentin",
                    "💝|Heart With Ribbon|cadeau,coeur,amour",
                    "💟|Heart Decoration|coeur,decoration,style",
                    "💌|Love Letter|lettre,coeur,romance",
                    "💤|Zzz|sommeil,nuits,repos",
                    "💢|Anger Symbol|colere,impact,comic",
                    "💥|Collision|boom,impact,bang",
                    "💦|Sweat Droplets|eau,effort,gouttes",
                    "💨|Dashing Away|vitesse,vent,mouvement",
                    "💫|Dizzy Symbol|etoiles,magie,vertige",
                    "💬|Speech Balloon|message,discussion,chat",
                    "🗨️|Left Speech Bubble|discussion,parole,message",
                    "🗯️|Right Anger Bubble|colere,parole,comic",
                    "💭|Thought Balloon|idee,penser,revasser",
                    "💮|White Flower|reussite,gratitude,merci"
                ].join('\n'),
            },
            {
                key: 'people',
                label: 'People & Body',
                block: [
                    "👋|Waving Hand|salut,bonjour,aurevoir",
                    "🤚|Raised Back Of Hand|salut,stop,main",
                    "🖐️|Hand With Fingers Splayed|main,stop,gestuelle",
                    "✋|Raised Hand|stop,main,attention",
                    "🖖|Vulcan Salute|prosper,longue,vie",
                    "👌|Ok Hand|ok,accord,main",
                    "🤌|Pinched Fingers|italien,precision,question",
                    "🤏|Pinching Hand|petit,dose,gestuelle",
                    "✌️|Victory Hand|victoire,paix,main",
                    "🤞|Crossed Fingers|chance,espoir,main",
                    "🤟|Love-You Gesture|amour,language,main",
                    "🤘|Sign Of The Horns|rock,concert,metal",
                    "🤙|Call Me Hand|telephone,aloha,contact",
                    "👈|Backhand Index Pointing Left|gauche,indiquer,main",
                    "👉|Backhand Index Pointing Right|droite,indiquer,main",
                    "👆|Backhand Index Pointing Up|haut,indiquer,main",
                    "🖕|Middle Finger|grossier,interdit,insulte",
                    "👇|Backhand Index Pointing Down|bas,indiquer,main",
                    "👍|Thumbs Up|ok,validation,like",
                    "👎|Thumbs Down|non,refus,dislike",
                    "✊|Raised Fist|solidarite,poing,force",
                    "👊|Oncoming Fist|poing,impact,check",
                    "🤛|Left-Facing Fist|poing,frappe,amical",
                    "🤜|Right-Facing Fist|poing,frappe,amical",
                    "👏|Clapping Hands|bravo,applaudir,soutien",
                    "🙌|Raising Hands|bravo,joie,victoire",
                    "👐|Open Hands|partage,accueil,main",
                    "🤲|Palms Up Together|priere,offrir,aide",
                    "🤝|Handshake|accord,partenariat,cooperation",
                    "🙏|Folded Hands|merci,priere,respect",
                    "✍️|Writing Hand|ecrire,signature,note",
                    "💅|Nail Polish|beaute,style,manucure",
                    "🤳|Selfie|photo,smartphone,partage",
                    "💪|Flexed Biceps|force,sport,muscle",
                    "🦾|Mechanical Arm|cyborg,robot,force",
                    "🦿|Mechanical Leg|prothese,robot,force",
                    "🦵|Leg|jambe,sport,corps",
                    "🦶|Foot|pied,marche,corps",
                    "👂|Ear|ecoute,son,corps",
                    "👃|Nose|odorat,corps,sante",
                    "🧠|Brain|idee,intelligence,neuro",
                    "🫀|Anatomical Heart|sante,medical,coeur",
                    "🫁|Lungs|respiration,sante,medical",
                    "🦷|Tooth|dentiste,sante,dent",
                    "🦴|Bone|os,squelette,science",
                    "👀|Eyes|voir,regard,attention",
                    "👁️|Eye|vision,regard,oeil",
                    "🧔|Person With Beard|personne,barbe,style",
                    "🧑|Person|neutre,personne,profil",
                    "👶|Baby|bebe,naissance,famille",
                    "🧒|Child|enfant,neutre,jeunesse",
                    "👦|Boy|enfant,garcon,famille",
                    "👧|Girl|enfant,fille,famille",
                    "👩|Woman|adulte,femme,famille",
                    "👨|Man|adulte,homme,famille",
                    "🧑‍🦰|Person With Red Hair|personne,cheveux,roux",
                    "🧑‍🦱|Person With Curly Hair|personne,cheveux,boucles",
                    "🧑‍🦳|Person With White Hair|personne,cheveux,blanc",
                    "🧑‍🦲|Person Bald|personne,cheveux,chauve",
                    "👱‍♀️|Woman Blond Hair|femme,blond,coiffure",
                    "👱‍♂️|Man Blond Hair|homme,blond,coiffure",
                    "👩‍🦰|Woman Red Hair|femme,roux,cheveux",
                    "👨‍🦰|Man Red Hair|homme,roux,cheveux",
                    "👩‍🦱|Woman Curly Hair|femme,boucles,cheveux",
                    "👨‍🦱|Man Curly Hair|homme,boucles,cheveux",
                    "👩‍🦳|Woman White Hair|femme,cheveux,blanc",
                    "👨‍🦳|Man White Hair|homme,cheveux,blanc",
                    "👩‍🦲|Woman Bald|femme,chauve,cheveux",
                    "👨‍🦲|Man Bald|homme,chauve,cheveux",
                    "🧑‍⚕️|Health Worker|medecin,infirmier,sante",
                    "👩‍⚕️|Woman Health Worker|medecin,infirmiere,sante",
                    "👨‍⚕️|Man Health Worker|medecin,infirmier,sante",
                    "🧑‍🎓|Student|etudiant,ecole,formation",
                    "👩‍🎓|Woman Student|etudiante,ecole,formation",
                    "👨‍🎓|Man Student|etudiant,ecole,formation",
                    "🧑‍🏫|Teacher|prof,formation,classe",
                    "👩‍🏫|Woman Teacher|professeur,classe,education",
                    "👨‍🏫|Man Teacher|professeur,classe,education",
                    "🧑‍⚖️|Judge|justice,tribunal,metier",
                    "👩‍⚖️|Woman Judge|justice,tribunal,metier",
                    "👨‍⚖️|Man Judge|justice,tribunal,metier",
                    "🧑‍🌾|Farmer|agriculture,ferme,metier",
                    "👩‍🌾|Woman Farmer|agriculture,ferme,metier",
                    "👨‍🌾|Man Farmer|agriculture,ferme,metier",
                    "🧑‍🍳|Cook|chef,cuisine,metier",
                    "👩‍🍳|Woman Cook|chef,cuisine,metier",
                    "👨‍🍳|Man Cook|chef,cuisine,metier",
                    "🧑‍🔧|Mechanic|reparation,metier,atelier",
                    "👩‍🔧|Woman Mechanic|reparation,metier,atelier",
                    "👨‍🔧|Man Mechanic|reparation,metier,atelier",
                    "🧑‍🏭|Factory Worker|industrie,metier,ouvrier",
                    "👩‍🏭|Woman Factory Worker|industrie,metier,ouvriere",
                    "👨‍🏭|Man Factory Worker|industrie,metier,ouvrier",
                    "🧑‍💼|Office Worker|bureau,metier,corporate",
                    "👩‍💼|Woman Office Worker|bureau,metier,manager",
                    "👨‍💼|Man Office Worker|bureau,metier,manager",
                    "🧑‍🔬|Scientist|science,laboratoire,recherche",
                    "👩‍🔬|Woman Scientist|science,laboratoire,recherche",
                    "👨‍🔬|Man Scientist|science,laboratoire,recherche",
                    "🧑‍💻|Technologist|dev,code,metier",
                    "👩‍💻|Woman Technologist|dev,code,metier",
                    "👨‍💻|Man Technologist|dev,code,metier",
                    "🧑‍🎤|Singer|musique,scene,metier",
                    "👩‍🎤|Woman Singer|musique,scene,metier",
                    "👨‍🎤|Man Singer|musique,scene,metier",
                    "🧑‍🎨|Artist|art,peinture,metier",
                    "👩‍🎨|Woman Artist|art,peinture,metier",
                    "👨‍🎨|Man Artist|art,peinture,metier",
                    "🧑‍✈️|Pilot|avion,metier,voyage",
                    "👩‍✈️|Woman Pilot|avion,metier,voyage",
                    "👨‍✈️|Man Pilot|avion,metier,voyage",
                    "🧑‍🚀|Astronaut|espace,metier,science",
                    "👩‍🚀|Woman Astronaut|espace,metier,science",
                    "👨‍🚀|Man Astronaut|espace,metier,science",
                    "🧑‍🚒|Firefighter|secours,metier,urgence",
                    "👩‍🚒|Woman Firefighter|secours,metier,urgence",
                    "👨‍🚒|Man Firefighter|secours,metier,urgence",
                    "👮|Police Officer|police,securite,metier",
                    "👮‍♀️|Woman Police Officer|police,securite,metier",
                    "👮‍♂️|Man Police Officer|police,securite,metier",
                    "🕵️|Detective|enquete,metier,espion",
                    "🕵️‍♀️|Woman Detective|enquete,metier,espion",
                    "🕵️‍♂️|Man Detective|enquete,metier,espion",
                    "💂|Guard|royaume,securite,metier",
                    "💂‍♀️|Woman Guard|royaume,securite,metier",
                    "💂‍♂️|Man Guard|royaume,securite,metier",
                    "🥷|Ninja|stealth,culture,japon",
                    "👷|Construction Worker|chantier,metier,securite",
                    "👷‍♀️|Woman Construction Worker|chantier,metier,securite",
                    "👷‍♂️|Man Construction Worker|chantier,metier,securite",
                    "🤴|Prince|royal,famille,couronne",
                    "👸|Princess|royal,famille,couronne",
                    "🤵|Person In Tuxedo|mariage,evenement,tenue",
                    "🤵‍♀️|Woman In Tuxedo|mariage,evenement,tenue",
                    "👰|Bride With Veil|mariage,evenement,tenue",
                    "👰‍♂️|Man With Veil|mariage,inclusif,tenue",
                    "👰‍♀️|Woman With Veil|mariage,tradition,tenue",
                    "🤰|Pregnant Woman|grossesse,famille,soin",
                    "🫃|Pregnant Man|grossesse,famille,inclusif",
                    "🫄|Pregnant Person|grossesse,famille,inclusif",
                    "🤱|Breast-Feeding|maternel,soin,bebe",
                    "👩‍🍼|Woman Feeding Baby|bebe,nourrir,soin",
                    "👨‍🍼|Man Feeding Baby|bebe,nourrir,soin",
                    "🧑‍🍼|Person Feeding Baby|bebe,nourrir,soin",
                    "🙇|Person Bowing|respect,reverence,salut",
                    "🙇‍♀️|Woman Bowing|respect,reverence,salut",
                    "🙇‍♂️|Man Bowing|respect,reverence,salut",
                    "💁|Person Tipping Hand|info,accueil,service",
                    "💁‍♀️|Woman Tipping Hand|info,accueil,service",
                    "💁‍♂️|Man Tipping Hand|info,accueil,service",
                    "🙅|Person Gesturing No|refus,non,stop",
                    "🙅‍♀️|Woman Gesturing No|refus,non,stop",
                    "🙅‍♂️|Man Gesturing No|refus,non,stop",
                    "🙆|Person Gesturing Ok|ok,accord,gestuelle",
                    "🙆‍♀️|Woman Gesturing Ok|ok,accord,gestuelle",
                    "🙆‍♂️|Man Gesturing Ok|ok,accord,gestuelle",
                    "🙋|Person Raising Hand|question,participer,main",
                    "🙋‍♀️|Woman Raising Hand|question,participer,main",
                    "🙋‍♂️|Man Raising Hand|question,participer,main",
                    "🧏|Deaf Person|accessibilite,inclusion,langue",
                    "🧏‍♀️|Deaf Woman|accessibilite,inclusion,langue",
                    "🧏‍♂️|Deaf Man|accessibilite,inclusion,langue",
                    "🙍|Person Frowning|triste,decu,visage",
                    "🙍‍♀️|Woman Frowning|triste,decu,visage",
                    "🙍‍♂️|Man Frowning|triste,decu,visage",
                    "🙎|Person Pouting|mecontent,visage,attitude",
                    "🙎‍♀️|Woman Pouting|mecontent,visage,attitude",
                    "🙎‍♂️|Man Pouting|mecontent,visage,attitude",
                    "👪|Family|famille,parents,enfants",
                    "👨‍👩‍👦|Family Man Woman Boy|famille,parents,enfant",
                    "👨‍👩‍👧|Family Man Woman Girl|famille,parents,enfant",
                    "👨‍👩‍👧‍👦|Family Man Woman Girl Boy|famille,parents,enfants",
                    "👨‍👩‍👦‍👦|Family Man Woman Boys|famille,parents,enfants",
                    "👨‍👩‍👧‍👧|Family Man Woman Girls|famille,parents,enfants",
                    "👨‍👨‍👦|Family Men Boy|famille,inclusif,enfant",
                    "👨‍👨‍👧|Family Men Girl|famille,inclusif,enfant",
                    "👨‍👨‍👧‍👦|Family Men Girl Boy|famille,inclusif,enfants",
                    "👨‍👨‍👦‍👦|Family Men Boys|famille,inclusif,enfants",
                    "👨‍👨‍👧‍👧|Family Men Girls|famille,inclusif,enfants",
                    "👩‍👩‍👦|Family Women Boy|famille,inclusif,enfant",
                    "👩‍👩‍👧|Family Women Girl|famille,inclusif,enfant",
                    "👩‍👩‍👧‍👦|Family Women Girl Boy|famille,inclusif,enfants",
                    "👩‍👩‍👦‍👦|Family Women Boys|famille,inclusif,enfants",
                    "👩‍👩‍👧‍👧|Family Women Girls|famille,inclusif,enfants",
                    "👨‍👦|Family Man Boy|famille,parent,enfant",
                    "👨‍👦‍👦|Family Man Boys|famille,parent,enfants",
                    "👨‍👧|Family Man Girl|famille,parent,enfant",
                    "👨‍👧‍👦|Family Man Girl Boy|famille,parent,enfants",
                    "👨‍👧‍👧|Family Man Girls|famille,parent,enfants",
                    "👩‍👦|Family Woman Boy|famille,parent,enfant",
                    "👩‍👦‍👦|Family Woman Boys|famille,parent,enfants",
                    "👩‍👧|Family Woman Girl|famille,parent,enfant",
                    "👩‍👧‍👦|Family Woman Girl Boy|famille,parent,enfants",
                    "👩‍👧‍👧|Family Woman Girls|famille,parent,enfants",
                    "🧑‍🤝‍🧑|People Holding Hands|amitie,groupe,inclusif",
                    "👭|Women Holding Hands|amitie,groupe,femmes",
                    "👫|Woman And Man Holding Hands|amitie,couple,marche",
                    "👬|Men Holding Hands|amitie,groupe,hommes",
                    "💑|Couple With Heart|amour,couple,romance",
                    "👩‍❤️‍👨|Couple Woman Man Heart|amour,couple,hetero",
                    "👩‍❤️‍👩|Couple Women Heart|amour,couple,femmes",
                    "👨‍❤️‍👨|Couple Men Heart|amour,couple,hommes",
                    "💏|Kiss|baiser,couple,romance",
                    "👩‍❤️‍💋‍👨|Kiss Woman Man|baiser,couple,hetero",
                    "👩‍❤️‍💋‍👩|Kiss Women|baiser,couple,femmes",
                    "👨‍❤️‍💋‍👨|Kiss Men|baiser,couple,hommes",
                    "💃|Woman Dancing|danse,soiree,fete",
                    "🕺|Man Dancing|danse,soiree,fete",
                    "🪩|Mirror Ball|danse,disco,soirée",
                    "🕴️|Person In Suit Levitating|cool,retro,danse"
                ].join('\n'),
            },
            {
                key: 'animals',
                label: 'Animals & Nature',
                block: [
                    "🐵|Monkey Face|singe,animal,jungle",
                    "🐒|Monkey|singe,animal,foret",
                    "🦍|Gorilla|gorille,animal,foret",
                    "🦧|Orangutan|orangutan,animal,foret",
                    "🐶|Dog Face|chien,animal,compagnon",
                    "🐕|Dog|chien,animal,compagnon",
                    "🦮|Guide Dog|chien,guide,assistance",
                    "🐕‍🦺|Service Dog|chien,service,assistance",
                    "🐩|Poodle|chien,toilettage,caniche",
                    "🐺|Wolf|loup,animal,sauvage",
                    "🦊|Fox|renard,animal,sauvage",
                    "🦝|Raccoon|raton,animal,nuit",
                    "🐱|Cat Face|chat,animal,compagnon",
                    "🐈|Cat|chat,animal,domestique",
                    "🐈‍⬛|Black Cat|chat,noir,animal",
                    "🦁|Lion|lion,animal,savane",
                    "🐯|Tiger Face|tigre,animal,sauvage",
                    "🐅|Tiger|tigre,animal,foret",
                    "🐆|Leopard|leopard,animal,safari",
                    "🐴|Horse Face|cheval,animal,ferme",
                    "🐎|Horse|cheval,animal,course",
                    "🦄|Unicorn|licorne,animal,magie",
                    "🫎|Moose|elan,animal,foret",
                    "🦓|Zebra|zebre,animal,savane",
                    "🦌|Deer|cerf,animal,foret",
                    "🦬|Bison|bison,animal,plaine",
                    "🐮|Cow Face|vache,animal,ferme",
                    "🐂|Ox|boeuf,animal,travail",
                    "🐃|Water Buffalo|buffle,animal,ferme",
                    "🐄|Cow|vache,animal,lait",
                    "🐷|Pig Face|cochon,animal,ferme",
                    "🐖|Pig|cochon,animal,ferme",
                    "🐗|Boar|sanglier,animal,foret",
                    "🐽|Pig Nose|cochon,animal,nez",
                    "🐏|Ram|belier,animal,ferme",
                    "🐑|Ewe|brebis,animal,laine",
                    "🐐|Goat|chevre,animal,ferme",
                    "🐪|Camel|chameau,animal,desert",
                    "🐫|Two-Hump Camel|chameau,desert,voyage",
                    "🦙|Llama|lama,animal,montagne",
                    "🦒|Giraffe|girafe,animal,savane",
                    "🐘|Elephant|elephant,animal,safari",
                    "🦣|Mammoth|mammouth,prehistoire,animal",
                    "🦏|Rhinoceros|rhino,animal,safari",
                    "🦛|Hippopotamus|hippopotame,animal,river",
                    "🐭|Mouse Face|souris,animal,petit",
                    "🐁|Mouse|souris,animal,petit",
                    "🐀|Rat|rat,animal,ville",
                    "🐹|Hamster|hamster,animal,compagnie",
                    "🐰|Rabbit Face|lapin,animal,paques",
                    "🐇|Rabbit|lapin,animal,rapide",
                    "🐿️|Chipmunk|tamia,animal,foret",
                    "🦫|Beaver|castor,animal,barrage",
                    "🦔|Hedgehog|herisson,animal,forest",
                    "🦇|Bat|chauvesouris,animal,nuit",
                    "🐻|Bear|ours,animal,foret",
                    "🐻‍❄️|Polar Bear|ours,glace,arctique",
                    "🐨|Koala|koala,animal,australie",
                    "🐼|Panda|panda,animal,bambou",
                    "🦥|Sloth|paresseux,animal,foret",
                    "🦦|Otter|loutre,animal,riviere",
                    "🦨|Skunk|mouffette,animal,odeur",
                    "🦘|Kangaroo|kangourou,animal,australie",
                    "🦡|Badger|blaireau,animal,foret",
                    "🦃|Turkey|dinde,animal,ferme",
                    "🐔|Chicken|poulet,animal,ferme",
                    "🐓|Rooster|coq,animal,ferme",
                    "🐣|Hatching Chick|poussin,naissance,animal",
                    "🐤|Chick|poussin,animal,ferme",
                    "🐥|Front-Facing Chick|poussin,animal,jaune",
                    "🐦|Bird|oiseau,animal,vol",
                    "🐧|Penguin|manchot,animal,antarctique",
                    "🕊️|Dove|colombe,paix,animal",
                    "🦅|Eagle|aigle,animal,rapace",
                    "🦆|Duck|canard,animal,ferme",
                    "🦢|Swan|cygne,animal,grace",
                    "🦉|Owl|hibou,animal,nuit",
                    "🦤|Dodo|dodo,animal,disparu",
                    "🦩|Flamingo|flamant,animal,rose",
                    "🦚|Peacock|paon,animal,plumes",
                    "🦜|Parrot|perroquet,animal,tropical",
                    "🪿|Goose|oie,animal,ferme",
                    "🪺|Nest With Eggs|nid,oiseau,oeufs",
                    "🐸|Frog|grenouille,animal,marais",
                    "🐊|Crocodile|crocodile,animal,riviere",
                    "🐢|Turtle|tortue,animal,ocean",
                    "🦎|Lizard|lezard,animal,desert",
                    "🐍|Snake|serpent,animal,foret",
                    "🐲|Dragon Face|dragon,mythe,asie",
                    "🐉|Dragon|dragon,mythe,asie",
                    "🦕|Sauropod|dinosaure,prehistoire,long",
                    "🦖|T-Rex|dinosaure,prehistoire,tyrannosaure",
                    "🐳|Spouting Whale|baleine,animal,ocean",
                    "🐋|Whale|baleine,animal,mer",
                    "🐬|Dolphin|dauphin,animal,mer",
                    "🦭|Seal|phoque,animal,mer",
                    "🐟|Fish|poisson,animal,mer",
                    "🐠|Tropical Fish|poisson,animal,tropical",
                    "🐡|Blowfish|poisson,animal,gonfle",
                    "🦈|Shark|requin,animal,mer",
                    "🐙|Octopus|pieuvre,animal,mer",
                    "🦑|Squid|calamar,animal,mer",
                    "🦐|Shrimp|crevette,animal,mer",
                    "🦞|Lobster|homard,animal,mer",
                    "🦀|Crab|crabe,animal,mer",
                    "🐚|Spiral Shell|coquillage,plage,mer",
                    "🪸|Coral|corail,mer,reef",
                    "🪼|Jellyfish|meduse,mer,animal",
                    "🐌|Snail|escargot,animal,pluie",
                    "🦋|Butterfly|papillon,animal,jardin",
                    "🐛|Bug|insecte,animal,foret",
                    "🐜|Ant|fourmi,insecte,colonie",
                    "🐝|Honeybee|abeille,insecte,miel",
                    "🪲|Beetle|scarabee,insecte,foret",
                    "🐞|Lady Beetle|coccinelle,insecte,jardin",
                    "🦗|Cricket|criquet,insecte,chanson",
                    "🪳|Cockroach|cafard,insecte,maison",
                    "🦟|Mosquito|moustique,insecte,piqure",
                    "🪰|Fly|mouche,insecte,ete",
                    "🪱|Worm|ver,insecte,sol",
                    "🦠|Microbe|microbe,germes,sante",
                    "🌵|Cactus|desert,plante,nature",
                    "🎄|Christmas Tree|sapin,arbre,hiver",
                    "🌲|Evergreen Tree|sapin,arbre,foret",
                    "🌳|Deciduous Tree|arbre,nature,foret",
                    "🌴|Palm Tree|palme,plage,tropical",
                    "🌱|Seedling|germe,plante,nature",
                    "🌿|Herb|plante,nature,arome",
                    "☘️|Shamrock|trefle,plante,chance",
                    "🍀|Four Leaf Clover|trefle,chance,plante",
                    "🎍|Pine Decoration|bambou,nouvelan,plante",
                    "🪴|Potted Plant|plante,interieur,decor",
                    "🍁|Maple Leaf|feuille,automne,nature",
                    "🍂|Fallen Leaf|feuille,automne,foret",
                    "🍃|Leaf Fluttering|feuille,vent,nature",
                    "🍄|Mushroom|champignon,foret,plante",
                    "🌰|Chestnut|chataigne,automne,foret",
                    "🪵|Wood|bois,foret,matiere",
                    "🪹|Empty Nest|nid,vide,oiseau",
                    "☀️|Sun|soleil,meteo,jour",
                    "🌤️|Sun Behind Small Cloud|meteo,soleil,nuage",
                    "⛅|Sun Behind Cloud|meteo,nuage,jour",
                    "🌥️|Sun Behind Large Cloud|meteo,nuage,jour",
                    "☁️|Cloud|meteo,nuage,temps",
                    "🌦️|Sun Behind Rain Cloud|pluie,meteo,soleil",
                    "🌧️|Cloud With Rain|pluie,meteo,temps",
                    "⛈️|Cloud With Lightning And Rain|orage,meteo,pluie",
                    "🌩️|Cloud With Lightning|orage,meteo,eclair",
                    "🌨️|Cloud With Snow|neige,meteo,hiver",
                    "❄️|Snowflake|neige,hiver,meteo",
                    "☃️|Snowman With Snow|neige,hiver,bonhomme",
                    "⛄|Snowman|neige,hiver,bonhomme",
                    "🌬️|Wind Face|vent,meteo,hiver",
                    "🌪️|Tornado|tornade,meteo,tempete",
                    "🌫️|Fog|brouillard,meteo",
                    "🌈|Rainbow|arcenciel,meteo,nature",
                    "🌂|Closed Umbrella|parapluie,pluie,accessoire",
                    "☂️|Umbrella|pluie,meteo,accessoire",
                    "☔|Umbrella With Rain|pluie,meteo,nature",
                    "⚡|High Voltage|eclair,meteo,energie",
                    "🌊|Water Wave|vague,mer,nature",
                    "🔥|Fire|feu,energie,chaleur",
                    "💧|Droplet|eau,goutte,meteo",
                    "🌙|Crescent Moon|lune,nuit,meteo",
                    "🌕|Full Moon|lune,nuit,pleine",
                    "🌑|New Moon|lune,nuit,cycle",
                    "🌟|Glowing Star|etoile,nuit,magie",
                    "⭐|Star|etoile,nuit,magie",
                    "🌠|Shooting Star|etoile,fugitive,voeu",
                    "🌌|Milky Way|galaxie,espace,nuit",
                    "🛸|Flying Saucer|ovni,espace,alien"
                ].join('\n'),
            },
            {
                key: 'food',
                label: 'Food & Drink',
                block: [
                    "🍏|Green Apple|fruit,pomme,vert",
                    "🍎|Red Apple|fruit,pomme,sante",
                    "🍐|Pear|fruit,poire,vert",
                    "🍊|Tangerine|fruit,orange,vitamine",
                    "🍋|Lemon|fruit,citron,acide",
                    "🍌|Banana|fruit,banane,energie",
                    "🍉|Watermelon|fruit,pasteque,ete",
                    "🍇|Grapes|fruit,raisin,degustation",
                    "🍓|Strawberry|fruit,fraise,ete",
                    "🫐|Blueberries|fruit,myrtille,antioxydant",
                    "🍈|Melon|fruit,melon,ete",
                    "🍒|Cherries|fruit,cerise,ete",
                    "🍑|Peach|fruit,peche,rose",
                    "🥭|Mango|fruit,mangue,tropical",
                    "🍍|Pineapple|fruit,ananas,tropical",
                    "🥥|Coconut|fruit,noixcoco,tropical",
                    "🥝|Kiwi|fruit,kiwi,vitamine",
                    "🍅|Tomato|legume,tomate,cuisine",
                    "🍆|Eggplant|legume,aubergine,cuisine",
                    "🥑|Avocado|legume,avocat,brunch",
                    "🥦|Broccoli|legume,brocoli,vert",
                    "🥬|Leafy Green|legume,vert,sante",
                    "🥒|Cucumber|legume,concombre,salade",
                    "🌶️|Hot Pepper|piment,epice,rouge",
                    "🌽|Ear Of Corn|mais,legume,grille",
                    "🥕|Carrot|legume,carotte,orange",
                    "🧄|Garlic|ail,epice,cuisine",
                    "🧅|Onion|oignon,legume,cuisine",
                    "🥔|Potato|legume,pomme,terre",
                    "🍠|Roasted Sweet Potato|patate,douce,legume",
                    "🥐|Croissant|viennoiserie,patisserie,france",
                    "🥯|Bagel|pain,bagel,petitdejeuner",
                    "🍞|Bread|pain,boulangerie,aliment",
                    "🥖|Baguette Bread|baguette,pain,france",
                    "🥨|Pretzel|bretzel,sale,aperitif",
                    "🧀|Cheese Wedge|fromage,plateau,aliment",
                    "🥚|Egg|oeuf,proteine,cuisine",
                    "🍳|Cooking|poele,oeuf,cuisine",
                    "🧈|Butter|beurre,cuisine,toast",
                    "🥞|Pancakes|crepes,dejeuner,sirop",
                    "🧇|Waffle|gaufre,petitdejeuner,sirop",
                    "🥓|Bacon|bacon,petitdejeuner,proteine",
                    "🥩|Cut Of Meat|viande,steak,protein",
                    "🍗|Poultry Leg|poulet,viande,repas",
                    "🍖|Meat On Bone|viande,grill,barbecue",
                    "🌭|Hot Dog|sandwich,fastfood,barbecue",
                    "🍔|Hamburger|burger,repas,fastfood",
                    "🍟|French Fries|frites,fastfood,repas",
                    "🍕|Pizza|pizza,italie,repas",
                    "🫓|Flatbread|pain,galette,cuisine",
                    "🥪|Sandwich|sandwich,dejeuner,rapide",
                    "🥙|Stuffed Flatbread|kebab,wrap,repas",
                    "🧆|Falafel|falafel,vegetarien,repas",
                    "🌮|Taco|taco,mexique,repas",
                    "🌯|Burrito|burrito,mexique,repas",
                    "🫔|Tamale|tamale,mexique,repas",
                    "🥗|Green Salad|salade,vegetal,repas",
                    "🥘|Shallow Pan Of Food|paella,plat,partage",
                    "🫕|Fondue|fondue,fromage,convivial",
                    "🥫|Canned Food|conserve,repas,stock",
                    "🍝|Spaghetti|pates,italie,repas",
                    "🍜|Steaming Bowl|ramen,soupe,bol",
                    "🍲|Pot Of Food|soupe,ragoût,repas",
                    "🍛|Curry Rice|curry,riz,repas",
                    "🍣|Sushi|sushi,japon,repas",
                    "🍱|Bento Box|bento,japon,repas",
                    "🥟|Dumpling|ravioli,asie,repas",
                    "🍤|Fried Shrimp|crevette,tempura,frite",
                    "🍙|Rice Ball|onigiri,riz,japon",
                    "🍚|Cooked Rice|riz,repas,bol",
                    "🍘|Rice Cracker|galette,riz,snack",
                    "🍢|Oden|brochette,asie,repas",
                    "🍡|Dango|mochi,brochette,dessert",
                    "🍧|Shaved Ice|glace,ete,dessert",
                    "🍨|Ice Cream|glace,creme,dessert",
                    "🍦|Soft Ice Cream|glace,soft,dessert",
                    "🥧|Pie|tarte,dessert,partage",
                    "🧁|Cupcake|cupcake,dessert,patisserie",
                    "🍰|Shortcake|gateau,fraise,dessert",
                    "🎂|Birthday Cake|gateau,anniversaire,fete",
                    "🍮|Custard|flan,creme,dessert",
                    "🍭|Lollipop|bonbon,sucre,gouter",
                    "🍬|Candy|bonbon,sucre,douceur",
                    "🍫|Chocolate Bar|chocolat,douceur,dessert",
                    "🍿|Popcorn|popcorn,cinema,grignoter",
                    "🧋|Bubble Tea|bubble,the,boisson",
                    "🧃|Beverage Box|jus,boisson,portable",
                    "🧉|Mate|mate,boisson,energie",
                    "🧊|Ice Cube|glacons,froid,boisson",
                    "🥤|Cup With Straw|boisson,soda,frais",
                    "🥛|Glass Of Milk|lait,boisson,calcium",
                    "🫗|Pouring Liquid|versement,boisson,buvette",
                    "☕|Hot Beverage|cafe,the,chauffe",
                    "🫖|Teapot|the,service,boisson",
                    "🍵|Teacup Without Handle|the,matcha,boisson",
                    "🍶|Sake|sake,japon,alcool",
                    "🍺|Beer Mug|biere,alcool,cheers",
                    "🍻|Clinking Beer Mugs|biere,cheers,amis",
                    "🥂|Clinking Glasses|toast,celebration,champagne",
                    "🍷|Wine Glass|vin,alcool,degustation",
                    "🥃|Tumbler Glass|whisky,alcool,spiritueux",
                    "🍸|Cocktail Glass|cocktail,soiree,boisson",
                    "🍹|Tropical Drink|cocktail,tropical,vacances",
                    "🍾|Bottle With Popping Cork|champagne,celebration,fete",
                    "🍽️|Fork And Knife With Plate|repas,table,diner",
                    "🍴|Fork And Knife|couverts,repas,table",
                    "🥢|Chopsticks|baguettes,asie,repas",
                    "🧂|Salt|sel,assaisonnement,cuisine"
                ].join('\n'),
            },
            {
                key: 'travel',
                label: 'Travel & Places',
                block: [
                    "🗺️|World Map|carte,voyage,plan",
                    "🧭|Compass|boussole,orientation,aventure",
                    "🧳|Luggage|bagage,voyage,valise",
                    "🪪|Identification Card|identite,document,voyage",
                    "🛢️|Oil Drum|baril,industrie,transport",
                    "🚗|Automobile|voiture,voyage,route",
                    "🚕|Taxi|taxi,transport,ville",
                    "🚙|Sport Utility Vehicle|voiture,suv,route",
                    "🚌|Bus|bus,transport,public",
                    "🚎|Trolleybus|trolley,transport,public",
                    "🏎️|Racing Car|course,voiture,vitesse",
                    "🚓|Police Car|police,voiture,urgence",
                    "🚑|Ambulance|ambulance,urgence,sante",
                    "🚒|Fire Engine|pompiers,urgence,camion",
                    "🚐|Minibus|minibus,transport,groupe",
                    "🛻|Pickup Truck|pickup,transport,charge",
                    "🚚|Delivery Truck|livraison,transport,camion",
                    "🚛|Articulated Lorry|semi,transport,camion",
                    "🚜|Tractor|tracteur,agri,champ",
                    "🦽|Manual Wheelchair|mobilite,accessibilite,deplacement",
                    "🦼|Motorized Wheelchair|mobilite,accessibilite,vehicule",
                    "🛴|Kick Scooter|trottinette,urbain,transport",
                    "🛹|Skateboard|skate,urbain,glisse",
                    "🛼|Roller Skate|roller,patin,glisse",
                    "🚲|Bicycle|velo,transport,urbain",
                    "🛵|Motor Scooter|scooter,urbain,transport",
                    "🛺|Auto Rickshaw|tuktuk,transport,asie",
                    "🏍️|Motorcycle|moto,transport,vitesse",
                    "🚨|Police Car Light|alerte,urgence,signal",
                    "🚥|Horizontal Traffic Light|signalisation,route,feu",
                    "🚦|Vertical Traffic Light|signalisation,route,feu",
                    "🛣️|Motorway|autoroute,route,transport",
                    "🛤️|Railway Track|rail,transport,train",
                    "🅿️|Parking|parking,voiture,stationnement",
                    "🛑|Stop Sign|stop,signal,route",
                    "⛽|Fuel Pump|essence,station,carburant",
                    "🚧|Construction|travaux,route,securite",
                    "⚓|Anchor|bateau,port,maritime",
                    "⛵|Sailboat|voilier,mer,navigation",
                    "🛶|Canoe|canoe,pleinair,eau",
                    "🚤|Speedboat|bateau,vitesse,mer",
                    "🛥️|Motor Boat|bateau,plaisance,mer",
                    "🛳️|Passenger Ship|croisiere,mer,voyage",
                    "⛴️|Ferry|ferry,transport,mer",
                    "🚢|Ship|navire,mer,voyage",
                    "✈️|Airplane|avion,voyage,aerien",
                    "🛩️|Small Airplane|avion,leger,voyage",
                    "🛫|Airplane Departure|depart,avion,aeroport",
                    "🛬|Airplane Arrival|arrivee,avion,aeroport",
                    "🛸|Flying Saucer|ovni,espace,voyage",
                    "🚁|Helicopter|helico,transport,aerien",
                    "🚀|Rocket|fusée,espace,lancement",
                    "🛰️|Satellite|satellite,espace,communication",
                    "🛎️|Bellhop Bell|hotel,reception,service",
                    "🧺|Basket|pique-nique,panier,sortie",
                    "🏧|ATM Sign|banque,argent,retrait",
                    "🏠|House|maison,logement,domicile",
                    "🏡|House With Garden|maison,jardin,famille",
                    "🏘️|Houses|quartier,maisons,voisin",
                    "🏚️|Derelict House|maison,abandon,renovation",
                    "🏢|Office Building|bureau,immeuble,travail",
                    "🏣|Japanese Post Office|poste,japon,service",
                    "🏤|Post Office|poste,service,public",
                    "🏥|Hospital|hopital,sante,medical",
                    "🏦|Bank|banque,finance,argent",
                    "🏨|Hotel|hotel,sejour,voyage",
                    "🏩|Love Hotel|hotel,romance,sejour",
                    "🏪|Convenience Store|boutique,magasin,nuit",
                    "🏫|School|ecole,education,apprentissage",
                    "🏬|Department Store|magasin,centre,shopping",
                    "🏭|Factory|usine,industrie,production",
                    "🏯|Japanese Castle|chateau,japon,histoire",
                    "🏰|Castle|chateau,histoire,tourisme",
                    "💒|Wedding|mariage,chapelle,evenement",
                    "🗼|Tokyo Tower|tour,tokyo,monument",
                    "🗽|Statue Of Liberty|statue,newyork,monument",
                    "🗿|Moai|moai,ile,monument",
                    "🕌|Mosque|mosquee,lueur,culte",
                    "🕍|Synagogue|synagogue,culte,histoire",
                    "⛪|Church|eglise,culte,histoire",
                    "🛕|Hindu Temple|temple,hinde,culte",
                    "🕋|Kaaba|kaaba,culte,pelerinage",
                    "⛩️|Shinto Shrine|temple,japon,culte",
                    "🗾|Map Of Japan|japon,carte,geo",
                    "🎢|Roller Coaster|parc,attraction,loisir",
                    "🎡|Ferris Wheel|parc,manège,loisir",
                    "🎠|Carousel Horse|manège,parc,enfant",
                    "⛲|Fountain|fontaine,parc,ville",
                    "⛺|Tent|camping,nature,pleinair",
                    "🏕️|Camping|camping,nuit,nature",
                    "🏖️|Beach With Umbrella|plage,vacances,soleil",
                    "🏜️|Desert|desert,sable,voyage",
                    "🏝️|Desert Island|ile,plage,vacances",
                    "🏞️|National Park|parc,nature,randonnee",
                    "🏟️|Stadium|stade,sport,evenement",
                    "🏛️|Classical Building|batiment,histoire,musee",
                    "🏗️|Building Construction|construction,chantier,travaux",
                    "🧱|Brick|brique,materiaux,chantier",
                    "🪨|Rock|roche,nature,decor",
                    "🪵|Wood|bois,materiaux,construction",
                    "🛖|Hut|hutte,tradition,village",
                    "🌋|Volcano|volcan,nature,eruption",
                    "🏔️|Snow-Capped Mountain|montagne,neige,alpin",
                    "⛰️|Mountain|montagne,nature,randonnee",
                    "🗻|Mount Fuji|montfuji,japon,monument",
                    "🕰️|Mantelpiece Clock|horloge,temps,salon",
                    "🕑|Clock Two|horloge,heure,temps",
                    "🪂|Parachute|parachute,saut,air",
                    "🎑|Moon Viewing Ceremony|fete,lune,japon",
                    "🎆|Fireworks|feu,artifice,fete",
                    "🎇|Sparkler|etincelle,celebration,fete",
                    "🏮|Red Paper Lantern|lanterne,asie,fete",
                    "🪔|Diya Lamp|diwali,lumiere,fete",
                    "🕗|Clock|temps,heure,rendezvous"
                ].join('\n'),
            },
            {
                key: 'activities',
                label: 'Activities & Leisure',
                block: [
                    "⚽|Soccer Ball|football,sport,match",
                    "🏀|Basketball|basket,sport,equipe",
                    "🏈|American Football|football,americano,sport",
                    "⚾|Baseball|baseball,sport,match",
                    "🥎|Softball|softball,sport,lancer",
                    "🎾|Tennis|tennis,sport,raquette",
                    "🏐|Volleyball|volley,sport,plage",
                    "🏉|Rugby Football|rugby,sport,equipe",
                    "🥏|Flying Disc|frisbee,sport,pleinair",
                    "🎱|Pool 8 Ball|billard,jeu,salon",
                    "🪀|Yo-Yo|yoyo,jeu,retro",
                    "🏓|Ping Pong|pingpong,sport,raquette",
                    "🏸|Badminton|badminton,sport,raquette",
                    "🥊|Boxing Glove|boxe,sport,combat",
                    "🥋|Martial Arts Uniform|karate,judo,artmartial",
                    "🥅|Goal Net|but,sport,match",
                    "⛳|Flag In Hole|golf,sport,green",
                    "⛸️|Ice Skate|patinage,hiver,sport",
                    "🎿|Skis|ski,hiver,montagne",
                    "🛷|Sled|luge,hiver,neige",
                    "🥌|Curling Stone|curling,hiver,neige",
                    "🏂|Snowboarder|snowboard,hiver,glisse",
                    "🏄|Surfer|surf,mer,glisse",
                    "🏄‍♀️|Woman Surfing|surf,femme,glisse",
                    "🏄‍♂️|Man Surfing|surf,homme,glisse",
                    "🏊|Swimmer|natation,sport,piscine",
                    "🏊‍♀️|Woman Swimming|natation,femme,piscine",
                    "🏊‍♂️|Man Swimming|natation,homme,piscine",
                    "🚣|Person Rowing Boat|aviron,sport,bateau",
                    "🚣‍♀️|Woman Rowing Boat|aviron,femme,bateau",
                    "🚣‍♂️|Man Rowing Boat|aviron,homme,bateau",
                    "🚴|Person Biking|cyclisme,sport,velo",
                    "🚴‍♀️|Woman Biking|cyclisme,femme,velo",
                    "🚴‍♂️|Man Biking|cyclisme,homme,velo",
                    "🚵|Mountain Biking|vtt,sport,montagne",
                    "🚵‍♀️|Woman Mountain Biking|vtt,femme,montagne",
                    "🚵‍♂️|Man Mountain Biking|vtt,homme,montagne",
                    "🤼|People Wrestling|lutte,sport,combat",
                    "🤼‍♀️|Women Wrestling|lutte,femme,combat",
                    "🤼‍♂️|Men Wrestling|lutte,homme,combat",
                    "🤸|Person Cartwheeling|gymnastique,sport,acro",
                    "🤸‍♀️|Woman Cartwheeling|gymnastique,femme,acro",
                    "🤸‍♂️|Man Cartwheeling|gymnastique,homme,acro",
                    "🤺|Person Fencing|escrime,sport,combat",
                    "🤾|Person Playing Handball|handball,sport,match",
                    "🤾‍♀️|Woman Playing Handball|handball,femme,sport",
                    "🤾‍♂️|Man Playing Handball|handball,homme,sport",
                    "🤽|Person Playing Water Polo|waterpolo,sport,piscine",
                    "🤽‍♀️|Woman Playing Water Polo|waterpolo,femme,sport",
                    "🤽‍♂️|Man Playing Water Polo|waterpolo,homme,sport",
                    "🏋️|Person Lifting Weights|musculation,sport,force",
                    "🏋️‍♀️|Woman Lifting Weights|musculation,femme,force",
                    "🏋️‍♂️|Man Lifting Weights|musculation,homme,force",
                    "🧘|Person In Lotus Position|yoga,zen,meditation",
                    "🧘‍♀️|Woman In Lotus Position|yoga,femme,zen",
                    "🧘‍♂️|Man In Lotus Position|yoga,homme,zen",
                    "🏌️|Person Golfing|golf,sport,green",
                    "🏌️‍♀️|Woman Golfing|golf,femme,swing",
                    "🏌️‍♂️|Man Golfing|golf,homme,swing",
                    "🏇|Horse Racing|cheval,course,hippodrome",
                    "🤹|Person Juggling|jonglage,cirque,loisir",
                    "🤹‍♀️|Woman Juggling|jonglage,femme,cirque",
                    "🤹‍♂️|Man Juggling|jonglage,homme,cirque",
                    "🧗|Person Climbing|escalade,sport,montagne",
                    "🧗‍♀️|Woman Climbing|escalade,femme,montagne",
                    "🧗‍♂️|Man Climbing|escalade,homme,montagne",
                    "🧖|Person In Steamy Room|spa,bain,detente",
                    "🧖‍♀️|Woman In Steamy Room|sauna,femme,detente",
                    "🧖‍♂️|Man In Steamy Room|sauna,homme,detente",
                    "🏆|Trophy|trophee,victoire,prix",
                    "🥇|1st Place Medal|or,victoire,prix",
                    "🥈|2nd Place Medal|argent,victoire,prix",
                    "🥉|3rd Place Medal|bronze,victoire,prix",
                    "🏅|Sports Medal|medaille,sport,prix",
                    "🎖️|Military Medal|medaille,honneur,distinction",
                    "🎗️|Reminder Ribbon|ruban,soutien,cause",
                    "🎫|Ticket|billet,entree,evenement",
                    "🎟️|Admission Tickets|billets,evenement,concert",
                    "🎪|Circus Tent|cirque,spectacle,loisir",
                    "🎭|Performing Arts|theatre,scene,culture",
                    "🎨|Artist Palette|art,peinture,couleurs",
                    "🖌️|Paintbrush|peinture,outil,atelier",
                    "🖍️|Crayon|dessin,couleur,atelier",
                    "🎼|Musical Score|musique,partition,lecture",
                    "🎧|Headphone|musique,son,ecoute",
                    "🎷|Saxophone|musique,jazz,instrument",
                    "🎺|Trumpet|trompette,musique,fanfar",
                    "🎸|Guitar|guitare,musique,scene",
                    "🎻|Violin|violon,musique,classique",
                    "🥁|Drum|batterie,musique,rythme",
                    "🎹|Musical Keyboard|piano,clavier,musique",
                    "🎤|Microphone|micro,scene,chante",
                    "🎙️|Studio Microphone|studio,enregistrement,son",
                    "🎚️|Level Slider|audio,mixage,studio",
                    "🎛️|Control Knobs|audio,mixage,studio",
                    "🎬|Clapper Board|cinema,tournage,film",
                    "🎥|Movie Camera|cinema,video,tournage",
                    "🎦|Cinema|projecteur,film,salle",
                    "📽️|Film Projector|projecteur,retro,cinema",
                    "📹|Video Camera|camera,video,tournage",
                    "📸|Camera With Flash|photo,lumiere,shoot",
                    "📷|Camera|photo,image,appareil",
                    "🎞️|Film Frames|film,bobine,retros",
                    "🧩|Puzzle Piece|puzzle,jeu,logique",
                    "🎮|Video Game|gaming,console,loisir",
                    "🕹️|Joystick|gaming,retro,arcade",
                    "🎰|Slot Machine|casino,jeu,hasard",
                    "🎲|Game Die|jeu,societe,hasard",
                    "♟️|Chess Pawn|echec,jeu,strategie",
                    "🧿|Nazar Amulet|protection,porte,bonheur",
                    "🎯|Direct Hit|cible,jeu,precision",
                    "🎳|Bowling|bowling,loisir,piste",
                    "🎣|Fishing Pole|peche,loisir,nature",
                    "🪁|Kite|cerfvolant,pleinair,jeu",
                    "🪃|Boomerang|boomerang,jeu,retour",
                    "🪢|Knot|noeud,corde,scout",
                    "🪣|Bucket|seau,loisir,plage",
                    "🪤|Mouse Trap|piège,jeu,humour",
                    "🪘|Long Drum|musique,tam-tam,rythme",
                    "🪗|Accordion|musique,accordeon,folklore",
                    "🪇|Maracas|musique,maracas,rythme",
                    "🪈|Flute|musique,flute,instrument"
                ].join('\n'),
            },
            {
                key: 'objects',
                label: 'Objects & Gear',
                block: [
                    "⌚|Watch|montre,temps,accessoire",
                    "⏰|Alarm Clock|reveil,alarme,matin",
                    "⏱️|Stopwatch|chrono,temps,sport",
                    "⏲️|Timer Clock|minuteur,temps,cuisine",
                    "⌛|Hourglass Done|sablier,temps,attente",
                    "⏳|Hourglass Not Done|sablier,attente,progression",
                    "📶|Antenna Bars|signal,reseau,connexion",
                    "📱|Mobile Phone|telephone,smartphone,appareil",
                    "📲|Mobile Phone With Arrow|telephone,envoi,partage",
                    "☎️|Telephone|telephone,fixe,appel",
                    "📞|Telephone Receiver|telephone,appel,contact",
                    "📟|Pager|pager,retro,tech",
                    "📠|Fax Machine|fax,retro,office",
                    "📺|Television|tele,tv,media",
                    "📻|Radio|radio,audio,son",
                    "📡|Satellite Antenna|antenne,signal,communication",
                    "🛰️|Satellite|satellite,espace,orbite",
                    "🎥|Movie Camera|camera,video,tournage",
                    "📷|Camera|photo,image,appareil",
                    "📸|Camera With Flash|photo,lumiere,shoot",
                    "📹|Video Camera|camera,video,record",
                    "📼|Videocassette|cassette,retro,video",
                    "💻|Laptop|ordinateur,portable,travail",
                    "🖥️|Desktop Computer|ordinateur,bureau,travail",
                    "🖨️|Printer|imprimante,office,document",
                    "⌨️|Keyboard|clavier,ordinateur,peripherique",
                    "🖱️|Computer Mouse|souris,ordinateur,peripherique",
                    "🖲️|Trackball|trackball,ordinateur,peripherique",
                    "🎧|Headphone|casque,audio,musique",
                    "🔈|Speaker Low Volume|haut-parleur,audio,son",
                    "🔉|Speaker Medium Volume|haut-parleur,audio,volume",
                    "🔊|Speaker High Volume|haut-parleur,audio,fort",
                    "📢|Loudspeaker|annonce,son,public",
                    "📣|Megaphone|annonce,voix,haut",
                    "🔔|Bell|cloche,son,alerte",
                    "🔕|Bell With Slash|silence,muet,cloche",
                    "🔌|Electric Plug|prise,electricite,energie",
                    "🔋|Battery|batterie,energie,charge",
                    "🪫|Low Battery|batterie,faible,alerte",
                    "💡|Light Bulb|idee,lumiere,energie",
                    "🔦|Flashlight|lampe,torche,lumiere",
                    "🕯️|Candle|bougie,lumiere,ambiance",
                    "🪔|Diya Lamp|diya,lumiere,fete",
                    "🧯|Fire Extinguisher|extincteur,securite,incendie",
                    "🛢️|Oil Drum|baril,carburant,energie",
                    "🧰|Toolbox|boite,outil,bricolage",
                    "🧲|Magnet|aimant,science,force",
                    "🪛|Screwdriver|tournevis,outil,bricolage",
                    "🔧|Wrench|cle,outil,reparation",
                    "🔩|Nut And Bolt|boulon,fixation,atelier",
                    "⚙️|Gear|rouage,mecanique,systeme",
                    "🛠️|Hammer And Wrench|reparation,outil,atelier",
                    "⚒️|Hammer And Pick|mine,outil,chantier",
                    "🗜️|Clamp|serre,atelier,pression",
                    "🪚|Carpentry Saw|scie,outil,bois",
                    "🪓|Axe|hache,outil,bois",
                    "🔨|Hammer|marteau,outil,bricolage",
                    "⛏️|Pick|pioche,outil,miner",
                    "🪤|Mouse Trap|piege,maison,controle",
                    "🪜|Ladder|echelle,bricolage,hauteur",
                    "🪝|Hook|crochet,outil,suspension",
                    "🧱|Brick|brique,construction,mur",
                    "🪨|Rock|roche,pierre,construction",
                    "🪵|Wood|bois,ressource,construction",
                    "🧮|Abacus|boulier,calcul,education",
                    "🪙|Coin|piece,monnaie,finance",
                    "💰|Money Bag|argent,sac,finance",
                    "💳|Credit Card|carte,paiement,banque",
                    "💴|Banknote With Yen|billet,argent,yen",
                    "💶|Banknote With Euro|billet,argent,euro",
                    "💷|Banknote With Pound|billet,argent,livre",
                    "💵|Banknote With Dollar|billet,argent,dollar",
                    "💸|Money With Wings|argent,depense,perte",
                    "🧾|Receipt|ticket,preuve,achat",
                    "🪪|Identification Card|identite,carte,identifiant",
                    "💼|Briefcase|porte-documents,bureau,travail",
                    "✉️|Envelope|courrier,message,mail",
                    "📧|E-Mail|email,mail,message",
                    "📬|Mailbox With Raised Flag|courrier,reception,lettre",
                    "📭|Mailbox With Lowered Flag|courrier,attente,lettre",
                    "📮|Postbox|boite,poste,lettre",
                    "📦|Package|colis,livraison,paquet",
                    "🗳️|Ballot Box With Ballot|vote,election,urne",
                    "📥|Inbox Tray|boite,entree,courrier",
                    "📤|Outbox Tray|boite,sortie,courrier",
                    "📫|Closed Mailbox With Raised Flag|courrier,notification,poste",
                    "📪|Closed Mailbox With Lowered Flag|courrier,ferme,poste",
                    "📂|Open File Folder|dossier,organisation,documents",
                    "📁|File Folder|dossier,documents,bureau",
                    "🗂️|Card Index Dividers|classement,documents,bureau",
                    "🗃️|Card File Box|fichier,archive,documents",
                    "🗄️|File Cabinet|archives,bureau,rangement",
                    "🗑️|Wastebasket|poubelle,bureau,nettoyage",
                    "📄|Document|document,papier,texte",
                    "📃|Page With Curl|document,page,bureau",
                    "📜|Scroll|manuscrit,histoire,document",
                    "📑|Bookmark Tabs|marque-page,documents,organisation",
                    "📋|Clipboard|bloc,notes,controle",
                    "🗒️|Spiral Notepad|bloc,notes,ecriture",
                    "🗓️|Spiral Calendar|calendrier,agenda,planning",
                    "📆|Tear-Off Calendar|calendrier,date,planning",
                    "📅|Calendar|agenda,date,evenement",
                    "📊|Bar Chart|statistiques,rapport,analyse",
                    "📈|Chart Increasing|croissance,graphique,hausse",
                    "📉|Chart Decreasing|baisse,graphique,analyse",
                    "📇|Card Index|fichier,contact,rolodex",
                    "🖊️|Pen|stylo,ecriture,bureau",
                    "🖋️|Fountain Pen|stylo,plume,signature",
                    "✒️|Black Nib|stylo,plume,calligraphie",
                    "✏️|Pencil|crayon,ecriture,sketch",
                    "🖍️|Crayon|couleur,dessin,atelier",
                    "🖌️|Paintbrush|pinceau,peinture,art",
                    "📝|Memo|notes,ecriture,todo",
                    "🧷|Safety Pin|epingle,couture,fixer",
                    "📎|Paperclip|trombone,documents,attache",
                    "🖇️|Linked Paperclips|trombones,documents,ensemble",
                    "📌|Pushpin|punaise,notes,fixer",
                    "📍|Round Pushpin|punaise,position,carte",
                    "📏|Straight Ruler|regle,mesure,geometrie",
                    "📐|Triangular Ruler|equerre,mesure,geometrie",
                    "🧴|Lotion Bottle|flacon,cosmetique,beaute",
                    "🧼|Soap|savon,hygiene,nettoyage",
                    "🪥|Toothbrush|brosse,dent,hygiene",
                    "🪒|Razor|rasoir,hygiene,soin",
                    "🧽|Sponge|eponge,nettoyage,maison",
                    "🪣|Bucket|seau,nettoyage,maison",
                    "🪠|Plunger|deboucheur,plomberie,maison",
                    "🧹|Broom|balai,nettoyage,maison",
                    "🧺|Basket|panier,rangement,maison",
                    "🧻|Roll Of Paper|papier,toilette,consommable",
                    "🪑|Chair|chaise,meuble,interieur",
                    "🛋️|Couch And Lamp|canape,salon,interieur",
                    "🛏️|Bed|lit,chambre,repos",
                    "🪟|Window|fenetre,interieur,luminosite",
                    "🚪|Door|porte,interieur,maison",
                    "🪞|Mirror|miroir,reflet,decor",
                    "🖼️|Framed Picture|cadre,photo,decor",
                    "🪆|Nesting Dolls|poupee,russe,decor",
                    "🪅|Piñata|pinata,celebration,jeu",
                    "🎁|Wrapped Gift|cadeau,fete,surprise",
                    "🎀|Ribbon|ruban,decor,cadeau",
                    "🎗️|Reminder Ribbon|ruban,soutien,cause",
                    "🎎|Japanese Dolls|poupee,japon,decor",
                    "🎏|Carp Streamer|poisson,banniere,festival",
                    "🎐|Wind Chime|cloche,vent,zen",
                    "🎉|Party Popper|fete,celebration,confetti",
                    "🎊|Confetti Ball|fete,celebration,confetti",
                    "🎋|Tanabata Tree|bambou,voeux,japon",
                    "🎌|Crossed Flags|drapeau,cross,festival",
                    "🏮|Red Paper Lantern|lanterne,asie,decor",
                    "🛍️|Shopping Bags|shopping,achats,commerce",
                    "🛒|Shopping Cart|chariot,magasin,achats",
                    "🎒|Backpack|sac,ecole,bagage",
                    "👝|Clutch Bag|pochette,sac,mode",
                    "👛|Purse|porte-monnaie,sac,mode",
                    "👜|Handbag|sac,a-main,mode",
                    "🎓|Graduation Cap|diplome,etude,ceremonie",
                    "🎩|Top Hat|chapeau,style,evenement",
                    "👒|Woman’s Hat|chapeau,mode,soleil",
                    "🧢|Billed Cap|casquette,style,casual",
                    "👓|Glasses|lunettes,vision,accessoire",
                    "🕶️|Sunglasses|lunettes,soleil,style",
                    "👔|Necktie|cravate,mode,travail",
                    "👕|T-Shirt|vetement,cotton,casual",
                    "👖|Jeans|pantalon,vetement,denim",
                    "🧥|Coat|mantel,vetement,hiver",
                    "🧣|Scarf|echarpe,vetement,hiver",
                    "🧤|Gloves|gants,vetement,hiver",
                    "🧦|Socks|chaussettes,vetement,pied",
                    "👗|Dress|robe,mode,femme",
                    "👘|Kimono|kimono,vetement,japon",
                    "🩱|One-Piece Swimsuit|maillot,baignade,plage",
                    "👙|Bikini|bikini,plage,ete",
                    "🩳|Shorts|short,vetement,ete",
                    "🥻|Sari|sari,vetement,inde",
                    "🩲|Briefs|sous-vetement,maillot,plage",
                    "🥾|Hiking Boot|chaussure,rando,pleinair",
                    "👞|Man’s Shoe|chaussure,formel,mode",
                    "👟|Running Shoe|chaussure,sport,course",
                    "🥿|Flat Shoe|chaussure,femme,confort",
                    "👠|High-Heeled Shoe|talon,mode,femme",
                    "👡|Sandal|sandale,ete,mode",
                    "🩴|Thong Sandal|tongs,plage,ete",
                    "👢|Boot|botte,mode,hiver",
                    "👑|Crown|couronne,royale,prestige",
                    "💍|Ring|bague,engagement,bijou",
                    "💎|Gem Stone|bijou,diamant,luxe",
                    "🪬|Hamsa|amulette,protection,spirit",
                    "🧿|Nazar Amulet|amulette,protection,regard",
                    "📿|Prayer Beads|priere,mala,spirit",
                    "🔮|Crystal Ball|voyance,magie,avenir",
                    "🩺|Stethoscope|medical,sante,docteur",
                    "💉|Syringe|injection,medical,sante",
                    "💊|Pill|medicament,sante,pharma",
                    "🩹|Adhesive Bandage|pansement,sante,soin",
                    "🩼|Crutch|bequille,medical,soutien",
                    "🩻|X-Ray|radio,medical,diagnostic",
                    "🦽|Manual Wheelchair|mobilite,handicap,accessibilite",
                    "🦼|Motorized Wheelchair|mobilite,assistance,accessibilite",
                    "🛡️|Shield|bouclier,protection,securite",
                    "🔑|Key|cle,acces,serrure",
                    "🗝️|Old Key|clef,ancien,serrure",
                    "🔒|Locked|cadenas,ferme,secure",
                    "🔓|Unlocked|cadenas,ouvert,acces",
                    "🔐|Locked With Key|securise,ferme,protection",
                    "🔏|Locked With Pen|confidentiel,signature,secure",
                    "⚔️|Crossed Swords|epee,combat,arme",
                    "🗡️|Dagger|dague,arme,combat",
                    "🔪|Kitchen Knife|couteau,cuisine,outil",
                    "🪃|Boomerang|boomerang,jeu,retour",
                    "🧨|Firecracker|petard,celebration,fete",
                    "🪄|Magic Wand|magie,illusion,sorcier",
                    "🪩|Mirror Ball|disco,soiree,danse",
                    "🧸|Teddy Bear|nounours,enfant,jeu",
                    "🪀|Yo-Yo|jeu,retro,loisir",
                    "🕹️|Joystick|console,retro,arcade",
                    "🎮|Video Game|jeu,console,gaming",
                    "🔭|Telescope|telescope,astronomie,observation",
                    "🔬|Microscope|microscope,science,recherche",
                    "🧪|Test Tube|science,chimie,labo",
                    "🧫|Petri Dish|science,labo,culture",
                    "🧬|DNA|genetique,science,recherche",
                    "⚗️|Alembic|chimie,distillation,labo",
                    "🛎️|Bellhop Bell|reception,service,sonnette",
                    "🛗|Elevator|ascenseur,transport,batiment",
                    "🪧|Placard|pancarte,manifestation,affiche",
                    "🏷️|Label|etiquette,prix,tag",
                    "🪢|Knot|noeud,corde,attache"
                ].join('\n'),
            },
        ];

        return categories.map(function (category) {
            var items;
            if (Array.isArray(category.items)) {
                items = category.items.slice();
            } else {
                items = parseEmojiBlock(category.block || '');
            }
            return {
                key: category.key,
                label: category.label,
                items: items,
            };
        }).filter(function (category) {
            return Array.isArray(category.items) && category.items.length > 0;
        });
    })();


    var DEFAULT_EMOJI_HELPER = createEmojiHelper(DEFAULT_EMOJI_LIBRARY);



    var api = {
        createHelper: createEmojiHelper,
        getDefaultHelper: function () {
            return DEFAULT_EMOJI_HELPER;
        },
        getDefaultLibrary: function () {
            return DEFAULT_EMOJI_LIBRARY.map(function (category) {
                return {
                    key: category.key,
                    label: category.label,
                    items: category.items.map(function (item) {
                        return {
                            symbol: item.symbol,
                            name: item.name,
                            keywords: item.keywords.slice(),
                        };
                    }),
                };
            });
        },
        sanitizeValue: sanitizeEmojiValue,
        normalizeSearch: normalizeEmojiSearchValue,
        parseEmojiBlock: parseEmojiBlock,
        buildFlagEntries: buildFlagEntries,
        sliceGraphemes: sliceGraphemes,
        EmojiPickerField: EmojiPickerField,
    };

    global.MjRegMgrEmojiPicker = api;
    if (!global.MjRegMgrEmojiHelper) {
        global.MjRegMgrEmojiHelper = api;
    }

})(window);
