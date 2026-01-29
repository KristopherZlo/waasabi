<?php

return [

    [
        'user_key' => 'dasha',
        'type' => 'post',
        'slug' => 'battery-node-14-days',
        'title' => 'From sketch to field test: a battery sensor node in 14 days',
        'subtitle' => 'A realistic build log with power numbers and lessons learned.',
        'status' => 'done',
        'tags' => ['hardware', 'field', 'battery', 'process'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# From sketch to field test: a battery sensor node in 14 days

## Summary
We needed a sensor node that could survive a week outside, report every five minutes,
and be understandable to the next team that touches it. This is the log of how we built
it in two weeks and the decisions that mattered. It is not perfect, but it is real.

## Constraints we could not negotiate
- Two weeks end to end, including a field test.
- Off the shelf parts only, no custom PCB spin.
- Replaceable battery in the field, no soldering.
- Data must be readable without specialized tools.
- The node should fail loud, not silent.

## Architecture in one paragraph
We used a low power microcontroller, a simple temperature and motion sensor, and a small
radio module. Power was a single 18650 cell with a buck converter and a sleep-first
firmware design. The enclosure was printed, with a gasket and a drain path.

## Component choices and the tradeoffs
The MCU choice was about sleep current and tooling. We picked a part with a stable
sleep mode and a boring SDK, because the schedule could not handle exotic build tools.
For the radio we chose a module with good range but modest throughput. We send small
packets, so a heavy protocol would not help.

For sensors we avoided anything with a complex calibration step. One digital temperature
sensor and a motion sensor were enough for the first field test. We left space on the
connector for a future humidity sensor.

## Power budget, not guesses
We started with a budget and updated it after every milestone.
The numbers below are averaged, not peak.

| Component | Active mA | Sleep uA | Notes |
| --- | --- | --- | --- |
| MCU | 8.5 | 9 | 8 MHz, no debug |
| Radio TX | 42 | 0.5 | 3 second burst |
| Sensors | 2.1 | 0.8 | Polled on wake |
| Regulator | 0.0 | 24 | Quiescent draw |

Then we put this into a simple equation:

```
daily_mAh = (active_mA * active_minutes + sleep_mA * sleep_minutes) / 60
```

Our first version was 120 mAh per day, which gave us six to seven days in theory.
After we removed an LED and shortened the transmit window, we dropped to 88 mAh.
We also measured the actual sleep draw on a bench supply because datasheets lie.

## Firmware: the smallest loop that can fail safely
The firmware is intentionally boring. It sleeps, wakes, reads, transmits, and
sleeps again. We kept state in RAM and wrote a compact line to flash only when
there was a change in environment.

Key ideas:
- Start in sleep mode, wake only on timer.
- Validate sensor reads before transmit.
- If transmit fails, store a compact retry record.
- Use a fixed packet format that a shell script can parse.

Example packet format:

```
TS=1700000200 TEMP=21.6 MOTION=0 VBAT=3.82 RSSI=-71
```

We also included a simple self test that measures battery voltage and sensor ID.
If the self test fails, the node sends a single error packet and sleeps for an hour.

## Enclosure: water finds every shortcut
A printed enclosure always leaks at the seam. We handled this with a gasket and
an intentional drain channel so water would not collect against the PCB.
We also made the battery door a separate insert. The battery can be swapped
without exposing the sensor wiring.

We learned quickly:
- Do not trust a single gasket line.
- Place the vent higher than the PCB.
- Route the antenna away from the battery door.
- Print two versions and cut one open to see weak spots.

## Field test plan
We picked two locations and treated them differently:
- A sheltered spot under a roof edge.
- An exposed pole with direct rain.

Each node had:
- A labeled ID on the case.
- A paper log with install time.
- A tiny sticker with a QR code pointing to a local dashboard.

We added a simple ping test on the dashboard. If a node missed two consecutive
windows, the dashboard turned orange and stored the last battery reading.

## Results after seven days
The sheltered node missed one packet during a storm.
The exposed node missed seven packets across two days of high wind.
Both ran for the full week without a battery swap. The enclosures
showed no moisture marks inside.

Battery voltage on day seven was 3.62 V for the exposed node and 3.68 V for
the sheltered one. That suggests the estimate was close and we have a small
buffer for a longer deployment.

## Failure modes we saw
- A loose sensor connector caused three hours of flat readings.
- One node rebooted when the radio module drew a high peak.
- A gasket moved slightly during the battery swap and had to be re-seated.

None of these would have been caught without the field test.

## What we will change next time
- Add a low voltage cutoff to avoid deep discharge.
- Switch the regulator to reduce sleep draw by 10 to 15 uA.
- Move the motion sensor to a separate small board.
- Add a boot counter so we can track unexpected resets.

## Checklist we now reuse
- [ ] A power budget with real numbers, not estimates
- [ ] A packet format that a shell script can parse
- [ ] Clear battery access without touching the PCB
- [ ] A printed label with ID and install time
- [ ] A test plan for two environments
- [ ] A fail loud behavior for missing sensors
- [ ] A real world install photo to document placement

## Appendix: build notes
This is the notebook style log we kept. It is not glamorous, but it helps.

- Day 1: requirements, high level diagram, radio module selection.
- Day 2: breadboard, current draw check, packet format draft.
- Day 3: sleep mode verification, no debug pins, estimate read time.
- Day 4: enclosure draft, first gasket attempt, too thin.
- Day 5: second enclosure, added drain path.
- Day 6: field install, first packet seen.
- Day 7: storm test, one missed packet.
- Day 8: battery check, 3.78 V.
- Day 9: dashboard improvements, color for missing data.
- Day 10: rewrite install instructions in plain English.
- Day 11: reorder components, add spare connector.
- Day 12: reprint door, add finger pull.
- Day 13: field swap test, battery change in 90 seconds.
- Day 14: final summary and cleanup.

If you are doing something similar, try to keep the log. It makes it possible to
debug your own work without guessing.
MARKDOWN
    ],
    [
        'user_key' => 'katya',
        'type' => 'post',
        'slug' => 'calm-incident-review',
        'title' => 'Designing a calm incident review process for a small hardware team',
        'subtitle' => 'A low drama format that still produces clear actions and learning.',
        'status' => 'done',
        'tags' => ['process', 'review', 'team', 'ops'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Designing a calm incident review process for a small hardware team

## The problem we kept repeating
We shipped a new board revision, it failed in the field, and the discussion
immediately went sideways. People started to defend choices instead of describing
reality. We needed a review process that felt safe enough to tell the truth, and
structured enough to produce better behavior next time.

## Principles that made it work
We kept the principles short and repeated them at the top of every review.

1. Facts first, feelings second.
2. The goal is learning, not blame.
3. Small actions beat long debates.
4. The review is a product document, not a chat log.

## The format we landed on
We use a single shared document with five sections. Nothing else is allowed in the
first meeting. If it does not fit, we add an appendix after the meeting.

1. Timeline
2. What worked
3. What failed
4. Contributing factors
5. Actions and owners

## Timeline: the backbone of clarity
The timeline section forces people to talk about what happened, not what should
have happened. The rule is simple: one entry per hour, written in past tense.

Example timeline entry:
- 10:15 Field node stopped reporting. Last packet had VBAT=3.42.
- 11:05 Team confirmed the dashboard alarm was correct.
- 12:20 Site visit confirmed water ingress at the battery door.

If you cannot put a timestamp on an event, it goes in "contributing factors".

## What worked and what failed
This sounds simple, but it changes the tone. We require at least three bullets
in "what worked" before we allow people to write "what failed". That forces
the team to describe positive signals and avoids a spiral into blame.

Example "what worked" list:
- The alert fired within two minutes.
- The dashboard linked to install photos.
- The spare battery was on site.

Example "what failed" list:
- The door gasket tore after two swaps.
- The rain shield was missing on one install.
- The install checklist was not printed.

## Contributing factors are not the root cause
We removed the phrase "root cause" from the template. Most incidents have
multiple contributors and we do not have time to turn reviews into debates.
We use "contributing factors" and the rule is to list at least one process
factor and one design factor.

Example:
- Process: the review checklist was not stored with the kit.
- Design: the battery door can be opened without re-seating the gasket.

## Actions and owners
If there is no owner and no due date, it is not an action. We default to
small actions that can be finished in two days. Larger actions are split.

Action examples:
- Create a one page battery swap card. Owner: Mila. Due: Friday.
- Add a gasket alignment tab to the door. Owner: Ilya. Due: Next Tuesday.
- Add a moisture indicator sticker. Owner: Dasha. Due: Thursday.

## The review meeting is short on purpose
We cap the meeting at 45 minutes and do it in two passes.

Pass 1: read the document silently for 10 minutes.
Pass 2: discuss only the open questions or missing facts.

We do not debate actions in the meeting. If an action is unclear, the owner
takes it offline and updates the document within 48 hours.

## The smallest metrics that helped
We track only three numbers:
- Time to detection (hours)
- Time to mitigation (hours)
- Actions completed within two weeks (percent)

We display them on a small chart in the team space. It keeps the review honest
without turning it into a scorecard.

## Template you can copy
Below is the exact template we use. It is short for a reason.

```
# Incident review: [short name]
## Timeline
- [time] [event]

## What worked
- ...

## What failed
- ...

## Contributing factors
- Process:
- Design:
- Environment:

## Actions and owners
- [action] [owner] [due]
```

## Checklist we use every time
- [ ] Timeline entries are in past tense
- [ ] At least three "what worked" bullets
- [ ] Contributing factors include process and design
- [ ] Actions have owners and due dates
- [ ] Document posted within 48 hours

The point of this process is not perfection. It is to keep learning possible
when things go wrong. A calm review is a useful review.
MARKDOWN
    ],
    [
        'user_key' => 'mila',
        'type' => 'post',
        'slug' => 'search-that-does-not-lie',
        'title' => 'Search that does not lie: fuzzy matching, ranking, and trust signals',
        'subtitle' => 'How we built search results that feel accurate and honest.',
        'status' => 'done',
        'tags' => ['search', 'product', 'metrics', 'ux'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Search that does not lie: fuzzy matching, ranking, and trust signals

## Why search feels fragile
If search returns weak results even once, people stop trusting it. The fastest
way to break search is to show a high ranking result that does not match the
query. This post describes how we built a fuzzy search that still feels honest.

## Step 1: normalize the query
We lower case, remove punctuation, trim spaces, and split into tokens. We also
keep the original query string because it helps us highlight the exact words.

Normalization rules:
- Lower case everything.
- Replace punctuation with spaces.
- Collapse multiple spaces.
- Drop common stop words for ranking, but keep them for highlighting.

## Step 2: tokenize and store a compact index
We keep a token list for title, subtitle, and body. Title and subtitle are
stored as a list of tokens. Body is stored as a list of the top 200 keywords.
We keep this in a search index table that is updated on save.

The goal is to keep search fast without hiding the original text.

## Step 3: fuzzy matching without lying
Fuzzy matching helps with typos, but it creates bad matches if you are careless.
Our rule is simple: fuzzy matching can improve a score, but it cannot create a
match where there is no overlap. We require at least one exact token match.

We use these match types:
- Exact token match (strong)
- Prefix match (medium)
- Fuzzy match within one edit (weak)

We weight them 5, 2, and 1. Fuzzy never outranks an exact match.

## Step 4: ranking that explains itself
We learned that a ranking formula is easier to maintain if it can be described
in plain English. Ours is:

```
score = (title_match * 5 + subtitle_match * 3 + body_match * 1)
      + (recency_bonus) + (quality_bonus)
```

Recency bonus is small and fades after 30 days. Quality bonus is based on
upvotes and comments, but capped so it never overrides a good text match.

## Step 5: highlight what matched
When the results appear, we highlight the exact words that matched. If we only
matched a prefix or fuzzy token, we highlight that too and show a small "close
match" hint. It is a small detail, but it tells the user why a result appeared.

## Handling empty or short queries
For queries under three characters we do not use fuzzy matching. We show a
small suggestion to type more. This avoids a long list of irrelevant results.

## Measuring search quality
We track three metrics:
- Query success rate (click within 10 seconds)
- Reformulation rate (same user, new query within 30 seconds)
- Zero result rate

We do not use raw clicks as a success metric. A click can be an accident.

## Common failure modes
1. Too many synonyms. When every token has three alternatives, ranking collapses.
2. Allowing fuzzy match without an exact anchor token.
3. Overweighting popularity and burying relevant new content.
4. Index lag that returns stale data.

## A simple feedback loop
We added a one click "Not what I needed" link under the search results. It opens
a tiny form with the query pre-filled and allows a one sentence note. The raw
volume is low, but the signals are strong and easy to act on.

## Example evaluation script
We keep a small text file of queries and the expected top result. We run it as a
unit test for the search index. It is not perfect, but it prevents regressions.

```
query: "power noise pcb"
expected: "Best practices for power noise on mixed-signal PCBs"

query: "read time"
expected: "How do you estimate read time for long posts?"
```

## The trust signals that matter
When search is wrong, we make it obvious. The UI shows:
- The number of results.
- A clear "No results" state with suggestions.
- Highlighted matched terms.
- A small note if results are older than six months.

Search is a promise. The more honest it feels, the more people will use it.
MARKDOWN
    ],
    [
        'user_key' => 'sveta',
        'type' => 'post',
        'slug' => 'notification-system-people-dont-hate',
        'title' => 'A notification system people do not hate',
        'subtitle' => 'Respectful defaults, clear copy, and quiet hours that stay quiet.',
        'status' => 'done',
        'tags' => ['product', 'ux', 'notifications', 'writing'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# A notification system people do not hate

## The usual failure
Most notification systems fail for one reason: they talk too much. The second
reason is that the message does not tell you what to do. The third is that the
system ignores context, like time of day or current activity.

We built our notifications as a product, not a logging pipeline.

## Rules that drove every decision
1. Fewer notifications, higher trust.
2. Every notification has a next step.
3. Quiet hours are sacred.
4. The system never surprises users.

## Categories first, channels second
We started by defining categories that match real user intent:
- Direct replies and mentions
- Project feedback (reviews, comments)
- Moderation and reports
- Product updates

Each category has a default channel. Direct replies are in-app and email. Product
updates are in-app only by default. Moderation alerts can be pushed, but only for
verified accounts.

## Clear copy beats clever copy
We switched every notification to a simple format:

```
[Who] did [what] on [where]. [Why it matters].
```

Example:
"Mila left a review on Power module for a field hub. It includes a suggested fix."

This is not poetic, but it is fast to scan and easy to act on.

## Bundling reduces fatigue
We batch low priority events into a single digest every six hours. That single
choice reduced notification volume by 48 percent. We still send immediate alerts
for direct replies or moderation events.

## Quiet hours that stay quiet
Quiet hours are per user. If a user sets 22:00 to 07:00, we do not send during
that time. We also do not "catch up" with a flood at 07:01. We defer and bundle.

## Avoiding subtle spam
Even if the system sends one message, it can still feel spammy if the user did
not ask for it. We added a one click unsubscribe for each category at the bottom
of every email. We also show a "Why am I seeing this?" hint on in-app items.

## Notification states and honesty
We keep three states:
- New: unread and unseen
- Seen: opened but not acted on
- Read: explicitly marked as read

We do not auto mark as read when a user clicks a notification. That makes the
system feel like it is trying to clean up after itself.

## Measuring success
We track:
- Time to first open
- Action rate (did the user do the suggested action)
- Unsubscribe rate per category
- Notification volume per user per week

We do not chase raw open rate. A high open rate can mean too much noise.

## The notification template
Every notification is stored with structured fields. This makes it easy to
render in the UI and to filter by category.

```
{
  "type": "review",
  "actor": "mila",
  "target": "power-hub-night",
  "summary": "Left a review with two improvements",
  "action_url": "/projects/power-hub-night"
}
```

## A calm default set
We ship with conservative defaults:
- Email only for direct replies and mentions.
- In-app for everything else.
- Weekly digest for product updates.

The user can opt in to more, but we never force it.

## Checklist for shipping a new notification
- [ ] Does it map to a clear user action?
- [ ] Is the copy short and specific?
- [ ] Is the category correct?
- [ ] Would I want this message at 2 AM?
- [ ] Can the user disable it in one click?

If the answer to any of these is "no", we do not ship it. The best notification
system is the one users barely notice, until they need it.
MARKDOWN
    ],
    [
        'user_key' => 'admin',
        'type' => 'post',
        'slug' => 'moderation-workflow-playbook',
        'title' => 'Moderation workflow playbook for a growing community',
        'subtitle' => 'A practical queue, clear outcomes, and minimal drama.',
        'status' => 'done',
        'tags' => ['moderation', 'community', 'process', 'safety'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Moderation workflow playbook for a growing community

## Why a playbook matters
Moderation is a product surface. If it feels random or slow, people stop
reporting and trust breaks. A playbook is not about strict rules, it is about
consistent decisions and a faster response to real harm.

## The queue is the interface
We built a single moderation queue with a small set of filters:
- New reports (last 24 hours)
- High priority (safety risk, threats)
- Repeat offenders
- Appeals

Moderators always start in the "new reports" view. This keeps the flow honest.

## Report taxonomy that humans can follow
We keep report reasons short and plain:
- Harassment
- Spam
- Misinformation
- Illegal content
- Privacy violation
- Other

Each reason has a short internal checklist. No essay required.

## Severity levels
We added three levels:
- Low: cosmetic issues, light spam.
- Medium: repeated issues, off topic campaigns.
- High: threats, doxxing, illegal content.

Severity determines response time and who can approve the action.

## Decision outcomes
Every report ends with one of four outcomes:
- No action
- Content removed
- Account restricted
- Escalated to admin

We never keep decisions in free form. The moderator must pick one outcome and
write a one sentence explanation.

## The evidence rule
If it is not in the evidence section, it does not count. That keeps the review
clean. Evidence can be a link, a screenshot, or a log snippet, but it must be
stored with the report.

## Appeals are part of trust
We allow appeals only after a decision is made. Appeals go into a separate queue
and require a different moderator. The appeal decision must reference the original
evidence and add new evidence if the outcome changes.

## Rate limits and abuse prevention
Reports are throttled per user. If a user submits too many reports within an hour,
we flag the account for review and show a gentle reminder of report guidelines.

We also detect coordinated reports by grouping by time, target, and reason. If a
burst happens, we queue it but do not allow automated actions.

## The moderation log
Every action is logged with:
- Who acted
- What changed
- Why the decision was made
- Evidence links

This log is visible to admins and used in audits.

## Community transparency
We publish a monthly moderation summary. It is a simple list of counts and outcomes.
It does not name users. The goal is to show that reports are handled and that
policy is being applied consistently.

## Checklist for a clean workflow
- [ ] Reports must have a reason
- [ ] Evidence must be stored
- [ ] Outcome must be chosen from the fixed list
- [ ] Appeals reviewed by a different moderator
- [ ] High severity actions require a second approval

Moderation is not about winning arguments. It is about protecting the space
and keeping people safe. A playbook makes that possible at scale.
MARKDOWN
    ],
    [
        'user_key' => 'admin',
        'type' => 'post',
        'slug' => 'safe-role-promotion',
        'title' => 'Safe role promotion: letting trust grow without breaking security',
        'subtitle' => 'Signals, guardrails, and reversible promotions.',
        'status' => 'done',
        'tags' => ['roles', 'security', 'community', 'policy'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Safe role promotion: letting trust grow without breaking security

## The core tension
Communities need trusted members, but permissions are security boundaries.
If role changes are too strict, people feel blocked. If they are too loose,
abuse slips through. The goal is a gradual ladder with clear guardrails.

## We define trust signals
We use a small set of signals, each with a weight:
- Account age
- Completed profile
- Posts with positive feedback
- Reports resolved in good faith
- Consistent behavior (no moderation actions)

No single signal can trigger a promotion on its own.

## The ladder we use
1. User: default role, full read access, limited posting.
2. Maker: can publish projects and leave reviews.
3. Moderator: can act on reports, limited to their scope.
4. Admin: full access, always manual.

Promotions from user to maker can be automatic. Promotions to moderator are
manual and require an explicit invite.

## The promotion window
We review role promotion candidates weekly. The list is generated automatically
but a human confirms. This reduces the risk of a bad automatic change.

We also use a probation window: the new role is active, but sensitive actions
are rate limited for the first 14 days.

## Reversible by default
Every role promotion is reversible. If we detect unusual behavior, we can move
the user back to the previous role with one click. This is not a punishment, it
is a safety measure.

## Transparency without noise
We notify the user when a role changes and explain the new abilities in plain
language. We do not broadcast promotions publicly, because it creates status
pressure and invites gaming.

## Manual overrides
Sometimes you need to bypass the system. We allow manual promotions, but they
require a short note explaining why. The note is stored for audit.

## Example policy
Here is a simplified policy that we implemented:

```
if account_age >= 30 days
and posts_with_positive_feedback >= 3
and reports_resolved >= 2
and no_recent_flags
then candidate_for_maker = true
```

We treat it as a suggestion, not a command.

## Anti-gaming measures
- We ignore feedback spikes from the same small group.
- We ignore self-reports and self-resolved actions.
- We cap the weight of any single week of activity.

## Checklist before enabling automatic promotions
- [ ] Clear signals with weights
- [ ] Manual review window
- [ ] Probation limits for sensitive actions
- [ ] Reversal mechanism with audit log
- [ ] User notification with clear next steps

Trust is precious. A safe ladder lets the community grow while keeping security
boundaries intact.
MARKDOWN
    ],
    [
        'user_key' => 'nikita',
        'type' => 'post',
        'slug' => 'upvotes-incentives',
        'title' => 'Upvotes and incentives: designing feedback that does not distort',
        'subtitle' => 'Simple signals can still create healthy behavior when tuned well.',
        'status' => 'done',
        'tags' => ['product', 'community', 'metrics', 'design'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Upvotes and incentives: designing feedback that does not distort

## Why upvotes are not neutral
Upvotes create a loop. People see what gets rewarded and adjust their behavior.
If the system rewards volume over quality, you will get volume. If it rewards
hot takes, you will get heat. The goal is to shape incentives carefully.

## Problems we wanted to avoid
1. Herding: people upvote what is already popular.
2. Gaming: small groups boosting each other.
3. Fear: new users avoid posting because they expect silence.

## Small design choices that matter
We made a few decisions that reduced these effects:
- Hide total upvotes for the first hour.
- Show a "new and rising" section that ignores votes.
- Allow only one vote per user per post, no downvotes.

## Time decay instead of instant ranking
We use a gentle time decay so that older posts can still surface but new posts
get a fair chance. The formula is simple: score divided by the square root of
age in hours. It is not perfect, but it is predictable.

## Anti-gaming controls
We watch for clusters of votes within a short period. If five accounts that
joined the same day upvote the same post within minutes, we mark those votes
as suspicious and reduce their weight.

We do not ban for this automatically. We flag for review and look for context.

## Feedback without pressure
We added a "thanks" reaction that does not affect ranking. It allows readers
to give small appreciation without chasing numbers.

## Default view matters
The default feed is a mix of new, rising, and proven posts. If the default is
"top all time", the community feels frozen. If the default is "new only",
people assume quality is low. The mix keeps energy without losing standards.

## The upvote message
When a user upvotes for the first time, we show a small tooltip:
"Upvotes help others discover useful work. Use them for clarity, not just for
friends." It is tiny, but it sets a tone.

## Metrics that tell the truth
We track:
- Median upvotes per post
- Percent of posts with zero votes
- Vote distribution across users

If the top 5 percent of users get most of the votes, we know the system is
drifting toward popularity rather than usefulness.

## A short policy you can copy
- Upvotes signal usefulness, not agreement.
- We do not display leaderboards based on votes.
- We review suspicious voting patterns weekly.

## Checklist for a healthy voting system
- [ ] Delay vote counts for the first hour
- [ ] Mix new and proven posts in the default feed
- [ ] Monitor zero vote posts
- [ ] Provide a non ranking appreciation reaction
- [ ] Detect clustered voting patterns

The goal is not to remove incentives, but to align them with the behavior you
actually want.
MARKDOWN
    ],
    [
        'user_key' => 'mila',
        'type' => 'post',
        'slug' => 'writing-long-form-technical-posts',
        'title' => 'Writing long-form technical posts that people actually finish',
        'subtitle' => 'Structure, narrative, and a repeatable editing workflow.',
        'status' => 'done',
        'tags' => ['writing', 'docs', 'product', 'community'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Writing long-form technical posts that people actually finish

## The hidden reason people stop reading
Most long posts fail because the reader does not know where they are. The content
can be good, but the structure is muddy. A clear map matters more than clever
sentences.

## Start with a promise
Your first paragraph should explain what the reader will get. Be concrete.
"You will learn how to estimate power budget for a sensor node" is better than
"We explore energy constraints in embedded devices."

## The spine of a good post
We use a simple structure:
1. Context
2. Constraints
3. Approach
4. Results
5. Lessons

Everything else is optional. If a section does not serve the spine, cut it.

## Use headings as navigation
People scan long posts before they commit. Headings should answer questions:
- "What did we build?"
- "Why did we choose this part?"
- "What broke and how did we fix it?"

If your headings are vague, your post will feel heavy.

## Show a working example early
A short example gives the reader a handle. It can be a diagram, a code snippet,
or a summary table. Once they see the shape of your solution, they stay longer.

## Avoid the "and then" story
Chronological logs are tempting, but they are hard to read. Instead, group by
topic. You can still include a timeline in an appendix.

## Data builds trust
If you claim an improvement, show the number. Even a rough measurement is better
than a claim with no evidence. Numbers are not about ego, they are about clarity.

## Visuals that are worth the space
We use three visual types:
- One diagram that explains the system.
- One table with key metrics.
- One photo or screenshot that proves it happened.

If a visual does not help, it is noise.

## Editing workflow that scales
We use two passes:
1. Structural edit: move, cut, and reorder sections.
2. Line edit: tighten sentences and fix style.

We do not combine them. It makes editing slow and painful.

## Checklist for a long post
- [ ] Title states the outcome
- [ ] First paragraph states the promise
- [ ] Headings are questions or claims
- [ ] One early example
- [ ] Data or measurements included
- [ ] Clear conclusion with lessons

## A template you can copy
```
# Title with outcome

## Summary
One paragraph promise and result.

## Context and constraints
What you needed and why it was hard.

## Approach
The solution and the tradeoffs.

## Results
Numbers, evidence, and surprises.

## Lessons
What you would do again and what you would avoid.
```

## Final thought
Long posts are not about length. They are about care. When you make the structure
clear, the reader follows you all the way to the end.
MARKDOWN
    ],
    [
        'user_key' => 'ilya',
        'type' => 'post',
        'slug' => 'data-modeling-posts-comments-reviews',
        'title' => 'Data modeling for posts, comments, reviews, and saves',
        'subtitle' => 'A pragmatic schema for a community app that stays flexible.',
        'status' => 'done',
        'tags' => ['database', 'design', 'laravel', 'architecture'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Data modeling for posts, comments, reviews, and saves

## Start with the simplest nouns
We model only what we can name clearly. For a community app that means:
- Users
- Posts
- Comments
- Reviews
- Saves
- Votes

Each of these gets a table. We avoid generic "activity" tables early because they
become a dumping ground.

## Posts table
Posts need to support two types: projects and questions. We keep a single table
with a `type` column. We store `body_markdown` and render to HTML at view time.

Important columns:
- `type` (post or question)
- `slug` (unique, indexed)
- `title`, `subtitle`
- `status` (done, in_progress, paused)
- `tags` (json array)

## Comments table
Comments are attached to a post and can be threaded. We use an adjacency list
with `parent_id`. This is not perfect, but it is good enough for shallow threads.

Important columns:
- `post_slug` (string)
- `user_id`
- `body`
- `section` (optional)
- `parent_id` (nullable)

We store `post_slug` because it is stable and easy to query.

## Reviews table
Reviews are structured feedback with three fields. We store them as a dedicated
table because it allows validation and makes aggregation easier.

Fields:
- `improve`
- `why`
- `how`

## Saves and votes
We use pivot tables with unique constraints:
- `post_saves` (`user_id`, `post_id`)
- `post_upvotes` (`user_id`, `post_id`)

These tables are small and fast. They also allow easy counting.

## Indexing and performance
We add indexes where they matter:
- `posts.slug` unique
- `posts.type` for feeds
- `comments.post_slug` for comment lists
- `post_upvotes.post_id` for counts

We avoid over-indexing. Each index has a write cost.

## JSON or relational?
We use JSON for tags because tags are a small list and not the core of the app.
If tags become a critical feature, we will normalize them into a separate table.

## Example schema excerpt
```
posts(id, user_id, type, slug, title, subtitle, body_markdown, status, tags)
post_comments(id, post_slug, user_id, body, section, parent_id)
post_reviews(id, post_slug, user_id, improve, why, how)
post_saves(user_id, post_id)
post_upvotes(user_id, post_id)
```

## Guardrails in code
Data modeling is not just tables. We enforce rules in code:
- A user can upvote only once.
- A comment parent must belong to the same post.
- Only makers can create reviews.

These rules protect the database from messy data.

## Migration tips
If you change the schema, add a migration and a backfill. Do not try to fix data
in place by hand. Write a safe script and test it.

## Checklist for a stable data model
- [ ] Each table has a clear noun
- [ ] Unique constraints match the rules
- [ ] Indexes match query patterns
- [ ] Validation mirrors database rules
- [ ] Migrations include backfills when needed

A clean data model is the foundation of a calm product. It keeps the code simple
and the app predictable.
MARKDOWN
    ],
    [
        'user_key' => 'admin',
        'type' => 'post',
        'slug' => 'secure-report-pipeline',
        'title' => 'Secure report pipeline: from a user report to a resolved case',
        'subtitle' => 'A clear flow that protects users and prevents abuse.',
        'status' => 'done',
        'tags' => ['moderation', 'security', 'process', 'support'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Secure report pipeline: from a user report to a resolved case

## Reports are sensitive by default
Every report might contain personal data or a safety risk. We treat reports as
restricted records with limited access. Only moderators and admins can see them.

## Step 1: capture the report with context
The report form asks for:
- Reason
- Short description
- Optional evidence link

We auto attach:
- Reporter ID
- Target content ID
- Timestamp and IP hash

We do not store raw IPs in the report record.

## Step 2: deduplicate and group
Many reports refer to the same content. We group by target and reason. If there
is an existing open report, new reports are attached as supporting evidence.

## Step 3: triage
The triage step assigns a severity and a handler. We keep a short triage checklist:
- Is this a safety risk?
- Is the content illegal?
- Is there personal data exposure?

High severity reports are routed to admins.

## Step 4: investigation
Moderators review the content, the user history, and the evidence. We keep a
simple rule: if the evidence is weak, we ask for more instead of guessing.

## Step 5: decision and action
Actions are limited to a small set:
- Remove content
- Warn user
- Restrict account
- No action

The moderator must write a one sentence explanation. This keeps the log usable.

## Step 6: notify the reporter
We send a short status update. We do not share personal details about the target.
Example: "Thanks for reporting. We reviewed the content and took action."

## Step 7: close and archive
Closed reports are archived. We keep only the data we need for audits and
delete sensitive evidence after a fixed period.

## Abuse prevention
Report abuse is real. We apply:
- Rate limits by user
- Soft blocks for spammy reporters
- Manual review for high volume reporters

We never auto punish a report without human review.

## Audit readiness
Every report has a complete log:
- Timestamps
- Handler
- Decisions
- Evidence links

This helps during legal or policy audits.

## Checklist for a secure report pipeline
- [ ] Minimal report form with required reason
- [ ] Automatic context capture
- [ ] Grouping for duplicate reports
- [ ] Clear severity levels
- [ ] Limited action outcomes
- [ ] Safe reporter notifications
- [ ] Data retention policy

Reports are not just moderation tools. They are user trust tools. Treat them with
the same care you give to account security.
MARKDOWN
    ],
    [
        'user_key' => 'dasha',
        'type' => 'post',
        'slug' => 'observability-for-prototypes',
        'title' => 'Observability for prototypes: logs, metrics, and field telemetry',
        'subtitle' => 'What to measure when your system is not yet stable.',
        'status' => 'done',
        'tags' => ['hardware', 'testing', 'metrics', 'field'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Observability for prototypes: logs, metrics, and field telemetry

## Why prototypes still need observability
Prototypes fail in unexpected ways. Without logs and metrics, you end up guessing.
The goal is not perfect monitoring, it is enough signal to debug quickly.

## Start with a minimal event log
We log only the events that matter:
- Boot
- Sensor read
- Transmit success or failure
- Low battery threshold

Each event is a short line that can fit in a small buffer.

Example log line:
```
ts=1700000200 event=tx_ok rssi=-71 vbat=3.82
```

## Metrics that tell the truth
We track five metrics:
- Uptime percent
- Packet success rate
- Battery voltage trend
- Sensor error rate
- Time between reboots

We do not track anything else in early prototypes. More metrics adds noise.

## Field telemetry on a budget
We send a compressed daily summary, not raw logs. The summary includes:
- Min and max battery
- Count of failed transmissions
- Last known sensor readings

This keeps data costs low and avoids flooding the dashboard.

## Local capture for deep dives
We keep a small ring buffer in flash. When a node fails, we can dump the buffer
and see the last 200 events. This is the fastest way to find intermittent issues.

## Health checks that make sense
A health check is only useful if it measures a real failure. We use:
- "Last packet < 15 minutes" as a warning
- "Last packet < 60 minutes" as a failure

If the system is noisy, we widen the window. False alarms are worse than no alarm.

## Dashboards that encourage action
A good dashboard makes the next step obvious. We use color and short labels:
- Green: stable
- Orange: needs attention
- Red: needs a visit

Each device row links to the latest log and install photo.

## Debugging stories that changed our approach
One node rebooted every night at 02:00. The log showed a transmit failure every
time, so we looked at the radio. It turned out the modem was overheating in the
enclosure after the temperature dropped. We added a short delay before transmit.

Another node reported steady temperature but the data never changed. The ring
buffer revealed that the sensor read failed and the value was stale. We added a
simple "sensor_ok" flag in the packet to detect this.

## Checklist for observability in prototypes
- [ ] Minimal event log with key actions
- [ ] Five core metrics
- [ ] Daily summary instead of raw telemetry
- [ ] Ring buffer for last 200 events
- [ ] Health check windows tuned to reality
- [ ] Dashboard links to install photo and logs

Observability is a habit. If you build it in during the prototype, the system
will be far easier to debug when the stakes are higher.
MARKDOWN
    ],
    [
        'user_key' => 'katya',
        'type' => 'post',
        'slug' => 'release-checklist-hardware-software',
        'title' => 'A release checklist that covers hardware and software together',
        'subtitle' => 'One list, one owner, fewer surprises.',
        'status' => 'done',
        'tags' => ['release', 'process', 'hardware', 'software'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# A release checklist that covers hardware and software together

## Why split checklists fail
Hardware and software releases are deeply connected. If firmware changes and the
hardware test plan does not, you get surprises. A single release checklist keeps
the team aligned and reduces missed steps.

## The release owner
Every release has one owner. That person is responsible for the checklist and
for clearing blockers. The owner is not the person doing every task. They are the
person who makes sure the tasks are done.

## The checklist structure
We use four sections:
1. Build and test
2. Integration
3. Documentation
4. Rollout

Each section has a short list of items. No single item should take more than a day.

## Build and test
- [ ] Firmware builds in a clean environment
- [ ] Hardware test fixture passes for all new boards
- [ ] Battery and power tests updated for new parts
- [ ] Regression tests pass

## Integration
- [ ] Firmware matches the backend expectations
- [ ] API versions match the device firmware
- [ ] Dashboard shows the new metrics
- [ ] Error codes documented and mapped

## Documentation
- [ ] Install guide updated with photos
- [ ] Field swap procedure updated
- [ ] Support team briefed on changes
- [ ] Release notes written in plain language

## Rollout
- [ ] Staging device updated and monitored for 24 hours
- [ ] First field install scheduled
- [ ] Rollback plan written
- [ ] Monitoring dashboard checked

## A release day timeline
We keep the day short and structured:
- Morning: final build and verification
- Midday: staging update
- Afternoon: field update
- End of day: review and log

If the timeline slips, we pause the release. A rushed release is a broken release.

## Checklist hygiene
We review the checklist after every release. If an item never catches issues,
we remove it. If an issue occurred that was not on the list, we add it.

## The simplest status board
We track releases with a simple board:
- To do
- In progress
- Done
- Blocked

Blocked items are the only items that get attention in standups.

## Checklist for adopting a single release list
- [ ] One owner for the release
- [ ] Four sections with small tasks
- [ ] Clear staging step
- [ ] Documentation included
- [ ] Rollback plan written

The checklist is not a bureaucracy tool. It is a memory aid. It helps the team
ship together, not separately.
MARKDOWN
    ],
    [
        'user_key' => 'timur',
        'type' => 'post',
        'slug' => 'laravel-performance-shared-hosting',
        'title' => 'Laravel performance on shared hosting: what actually moves the needle',
        'subtitle' => 'Practical steps that help without expensive infrastructure.',
        'status' => 'done',
        'tags' => ['laravel', 'performance', 'infra', 'ops'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Laravel performance on shared hosting: what actually moves the needle

## The reality of shared hosting
Shared hosting is constrained. You cannot tune everything, and you do not control
the OS. That means performance comes from small, safe improvements.

## Start with caching
The fastest request is the one you do not compute.

Checklist:
- Enable config caching (`php artisan config:cache`)
- Enable route caching (`php artisan route:cache`)
- Use view caching when possible

These are safe and usually provide immediate wins.

## Reduce database chatter
Many Laravel performance problems are just N+1 queries. Use `with()` and
`load()` to eager load relationships. Add indexes on columns that appear in
filters and order clauses.

Example:
```
Post::query()->with(['user', 'comments'])->latest()->take(20)->get();
```

## Add query logging in development only
Use query logs in local development to catch noisy endpoints. Do not enable
query logging in production. It will slow you down and fill logs.

## Use queues for slow tasks
Email sending, image processing, and heavy calculations should go to queues.
Even on shared hosting you can use the database queue and a cron worker.

## Optimize assets
Minify CSS and JS. Serve images in modern formats. Every byte counts when your
server is not powerful. Use a simple build step for assets and avoid heavy
front end libraries if you do not need them.

## Use OPcache if available
Many shared hosts have OPcache. If you can enable it, do it. It reduces PHP
startup cost and saves CPU.

## Monitoring without heavy tools
Use a simple request log with response time. A small middleware that records
duration for slow requests can help you spot issues without complex tooling.

## A minimal performance checklist
- [ ] Cache config and routes
- [ ] Eager load relationships
- [ ] Index common query columns
- [ ] Queue slow tasks
- [ ] Minify assets
- [ ] Enable OPcache if possible

Shared hosting can still feel fast. The key is to keep the app simple and to
remove waste where it matters most.
MARKDOWN
    ],
    [
        'user_key' => 'mila',
        'type' => 'post',
        'slug' => 'knowledge-base-that-scales',
        'title' => 'Building a knowledge base that scales: tags, read later, and review loops',
        'subtitle' => 'A calm structure that helps people find help without getting lost.',
        'status' => 'done',
        'tags' => ['knowledge', 'search', 'writing', 'product'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Building a knowledge base that scales: tags, read later, and review loops

## The first mistake
Most knowledge bases start as a list of articles. Then the list grows and
nothing is findable. The fix is not more categories. The fix is a small
structure that scales.

## We start with three content types
1. Guides: step by step instructions.
2. References: short factual pages.
3. Policies: rules and expectations.

Mixing them makes search messy. Keeping them separate makes writing easier.

## Tags are for discovery, not organization
We keep tags light. Each article gets two to five tags and they are chosen from
a short list. We avoid free form tags because they create duplicates.

## Read later is part of the system
People do not read everything on the first visit. A read later list lets them
collect useful content without losing their place.

We surface read later on every article and on search results.

## Review loops keep content alive
Every article has a review date. We show the date to the author and to admins.
If the review date passes, the article is flagged for update.

We do not delete old content, we update it or mark it as historical.

## Search is the front door
We design the search box as the main entry. People should be able to find the
right article even if they do not know the exact title.

We use fuzzy search and highlight the matched terms.

## Writing guidelines we follow
- Start with a promise and a short summary.
- Use short sentences.
- Add a "Next step" section.
- Include at least one screenshot or diagram.

## Feedback loop
Every article has a "Was this helpful?" prompt. We only ask for a single click.
If the answer is "no", we show a tiny optional text field.

## Metrics that matter
- Search success rate
- Percentage of articles reviewed on time
- Read later usage rate

We do not chase page views. We chase successful resolutions.

## Checklist for a scalable knowledge base
- [ ] Clear content types
- [ ] Limited tag vocabulary
- [ ] Read later available everywhere
- [ ] Review dates visible
- [ ] Search as the primary entry
- [ ] Lightweight feedback loop

A knowledge base is a product. Treat it with care and it will keep paying back.
MARKDOWN
    ],
    [
        'user_key' => 'admin',
        'type' => 'post',
        'slug' => 'security-by-default-community',
        'title' => 'Security by default for community platforms',
        'subtitle' => 'Threat modeling and practical safeguards that protect trust.',
        'status' => 'done',
        'tags' => ['security', 'community', 'auth', 'policy'],
        'cover_url' => '/images/cover-gradient.svg',
        'body_markdown' => <<<'MARKDOWN'
# Security by default for community platforms

## Security is a product decision
Security is not just code. It is what you allow, how you handle mistakes, and how
fast you respond. The safest system is one that makes abuse hard and recovery easy.

## Start with a threat model
List the likely threats:
- Account takeover
- Spam and bot attacks
- Data scraping
- Harassment and doxxing

For each threat, define a mitigation and a monitoring signal.

## Authentication basics
We require:
- Strong password rules
- Email verification
- Password reset with short lived tokens
- Login throttling

We also support two factor auth for moderators and admins.

## Input validation and output escaping
Every user input is validated on the server. We escape output by default. We do
not allow raw HTML in posts. Markdown is parsed and sanitized.

## Rate limiting and abuse detection
We rate limit:
- Login attempts
- Posting and commenting
- Report submissions

We also detect patterns like repeated content or rapid account creation.

## Role based access control
Permissions are based on roles. We keep the role list small and we do not allow
custom permission editing for standard users.

## Secure defaults in the UI
We hide sensitive actions behind confirmation dialogs. We do not auto publish
changes without review. We show warning banners for risky operations.

## Logging and monitoring
We log:
- Failed login attempts
- Permission changes
- Report actions

Logs are stored securely and rotated regularly. Monitoring should alert on spikes.

## Data retention and privacy
We keep only the data we need. We remove sensitive attachments after a set period.
We store hashes instead of raw IPs.

## Incident response plan
We have a simple plan:
1. Contain the issue
2. Assess impact
3. Notify affected users
4. Fix and document

A simple plan beats no plan.

## Checklist for security by default
- [ ] Threat model defined and updated
- [ ] Login throttling enabled
- [ ] Server side validation for all inputs
- [ ] Sanitized output for user content
- [ ] Role based access control
- [ ] Audit logs for sensitive actions
- [ ] Data retention policy

Security is a habit. Build it into every feature and it will feel natural to users.
MARKDOWN
    ],
];
