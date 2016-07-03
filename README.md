# Characters

A mod for supporting multiple 'characters' underneath a standard SMF account, typically for roleplay purposes.

This handles most of the requirements of typical 'sub-account' systems rather than having real sub-accounts.

Features (not all implemented yet!):
 * Switch characters with a menu in the top area.
 * Characters can have their own avatars, signatures and theme choices.
 * Show an individual character as online rather than the parent account.
 * Access to boards is managed by groups, but groups are partially attached to characters as well as accounts - displaying multiple badges and reporting options available.
 * Edits to characters show up in the profile edit log.
 * Designate boards as 'out of character' so that only the 'main character' (aka account itself) can post there.
 * Merge accounts in case users register a second account, expecting typical sub-account behaviour.
 * Replace the members list with a characters list.

# Caveats
 * PHP 5.4 is required.
 * Requestable groups/joinable groups are only ever handled at account level, never character level.
 * Merging accounts does not preserve the source (from) account's likes or poll votes. PMs are preserved; PM labels are not.
 * If a given account is alerted twice in the same post through two different quotes, only one notification will be generated - for the first quote for that account.
 * Email notifications are currently not supported for character-specific notifications and may not be in the future.
 * Some queries may fail with PostgreSQL, but the use case for PostgreSQL is sufficiently limited this is not a problem.

# Disclaimer

This was built for one site in particular for roleplay use. If you want to run a roleplay site with this mod, there are a few caveats.

 * This was built for SMF 2.1 only. I have neither the time or interest to build (or maintain) a 2.0 version.
 * Support is largely nil. I'll fix legitimate bugs, but new features or other improvements are unlikely to materialise.
 * It's not designed for existing sites with existing accounts to be merged into 'characters'. It's designed to be used at the start of the community. This is not going to change, sorry. That said, it would be feasible to use the merge feature to solve this, just very time-consuming.
 * If it doesn't work how you'd like, I'm sorry, but you're on your own, it's built for one specific site and their needs.
 * This is not a good guide of how to write a mod. It does things in a quick and dirty way in most places.
 * If you want to build another version of this mod, it's BSD licensed so you can go nuts, you just can't remove my name from it and can't claim it entirely as your own.
