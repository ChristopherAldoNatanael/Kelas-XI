package com.christopheraldoo.lazycolumn

data class Hero(
    val id: Int,
    val name: String,
    val realName: String,
    val emoji: String,
    val description: String
)

// Sample data untuk superhero dengan emoji
val heroList = listOf(
    // Marvel Heroes
    Hero(1, "Thor", "Chris Hemsworth", "⚡", "God of Thunder"),
    Hero(2, "Iron Man", "Robert Downey Jr", "🤖", "Genius Billionaire"),
    Hero(3, "Captain America", "Chris Evans", "🛡️", "Super Soldier"),
    Hero(4, "Black Widow", "Scarlett Johansson", "🕷️", "Master Spy"),
    Hero(5, "Hulk", "Mark Ruffalo", "💚", "The Incredible Hulk"),
    Hero(6, "Spider-Man", "Tom Holland", "🕸️", "Web Slinger"),
    Hero(7, "Doctor Strange", "Benedict Cumberbatch", "🔮", "Master of Mystic Arts"),
    Hero(8, "Black Panther", "Chadwick Boseman", "🐾", "King of Wakanda"),
    Hero(9, "Captain Marvel", "Brie Larson", "⭐", "Cosmic Powered Hero"),
    Hero(10, "Ant-Man", "Paul Rudd", "🐜", "Size-Changing Hero"),
    Hero(11, "Wasp", "Evangeline Lilly", "🐝", "Flying Hero"),
    Hero(12, "Vision", "Paul Bettany", "💎", "Synthetic Being"),
    Hero(13, "Scarlet Witch", "Elizabeth Olsen", "🔴", "Reality Manipulator"),
    Hero(14, "Winter Soldier", "Sebastian Stan", "❄️", "Enhanced Assassin"),
    Hero(15, "Falcon", "Anthony Mackie", "🦅", "Winged Warrior"),
    
    // DC Heroes
    Hero(16, "Superman", "Henry Cavill", "💙", "Man of Steel"),
    Hero(17, "Batman", "Ben Affleck", "🦇", "Dark Knight"),
    Hero(18, "Wonder Woman", "Gal Gadot", "⚔️", "Amazon Princess"),
    Hero(19, "The Flash", "Ezra Miller", "⚡", "Fastest Man Alive"),
    Hero(20, "Aquaman", "Jason Momoa", "🌊", "King of Atlantis"),
    Hero(21, "Green Lantern", "Ryan Reynolds", "💚", "Cosmic Guardian"),
    Hero(22, "Cyborg", "Ray Fisher", "🤖", "Half Human Half Machine"),
    
    // Other Heroes
    Hero(23, "Deadpool", "Ryan Reynolds", "😈", "Merc with a Mouth"),
    Hero(24, "Wolverine", "Hugh Jackman", "🔪", "Adamantium Claws"),
    Hero(25, "Professor X", "Patrick Stewart", "🧠", "Telepathic Leader"),
    Hero(26, "Jean Grey", "Sophie Turner", "🔥", "Phoenix Force"),
    Hero(27, "Storm", "Halle Berry", "⛈️", "Weather Goddess"),
    Hero(28, "Nightcrawler", "Kodi Smit-McPhee", "👹", "Teleporting Mutant"),
    Hero(29, "Magneto", "Michael Fassbender", "🧲", "Master of Magnetism"),
    Hero(30, "Quicksilver", "Evan Peters", "💨", "Super Speed")
)
