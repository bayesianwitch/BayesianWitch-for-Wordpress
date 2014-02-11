=== BayesianWitch ===
Contributors: stucchio
Tags: bandit algorithms, ab testing, affiliate marketing
Requires at least: 3.5
Tested up to: 3.8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Stable Tag: trunk


Use Bandit Algorithms to automaticaly optimize your call to action in affiliate marketing posts. Write it and forget it.

== Description ==

Bandit Algorithms are the next generation of A/B testing. BayesianWitch is a cloud service which uses Bandit Algorithms to increase engagement and clicks on your blog. BayesianWitch is aimed at using Bandit Algorithms to optimize each of your posts *individually*.

For example, you might be writing an affiliate blog about shoes. At the bottom of the post you wish to include an affiliate link. BayesianWitch will allow you to test different phrasing for the call to action (the paragraph of text where you embed the link) - for example:

- These beautiful red pumps will make a great accent with a little black dress.
- Go all red with a slinky red evening gown and these sexy red pumps.

BayesianWitch will figure out which call to action performs better and display that to most of your users. As a result, you get more clicks. It's all automatic - you set it up and forget about it.

A detailed tutorial with screenshots is available here: http://bayesianwitch.com/blog/2014/bayesianwitch_usage_tutorial.html

**Your BayesianWitch Account**

BayesianWitch is a cloud service like Disqus or Akismet. This means that you must create a BayesianWitch account (at http://bayesianwitch.com/wordpress/index.html ) and your blog will send data to our servers. BayesianWitch has both free and paid plans, depending on how large your blog is and how heavily you use our service.

BayesianWitch collects two types of data from you. First, whenever you set up a Bandit, Wordpress will send the contents of the bandit to our cloud servers. Second, in order to figure out which version of the bandit performs better, BaysianWitch must track the behavior of your users. We track your users by embedding a javascript tracking widget on your page, similar to Google Analytics or Chartbeat.

== Installation ==

You can download and install BayesianWitch using the built in WordPress plugin installer.

Activate BayesianWitch in the "Plugins" admin panel using the "Activate" link. You then need to set it up by plugging your BayesianWitch credentials. If you do not have the credentials, You will need to visit www.bayesianwitch.com and set up an account.

A detailed tutorial with screenshots is available here: http://bayesianwitch.com/blog/2014/bayesianwitch_install_tutorial.html

== Frequently Asked Questions ==

= How is this different from A/B testing? =

Bandit Algorithms are better than A/B testing for transient content - i.e., a single blog post. The reason is that A/B tests attempt to determine the one right answer, whereas Bandit Algorithms simply try to increase the number of clicks. As a result, Bandit Algorithms can increase your performance with fewer clicks than A/B testing.

Bandit Algorithms are automatic - once you write the post, you can forget about it. Even if your blog has a small number of readers per post (too small for A/B testing to work), Bandit Algorithms can increase the number of clicks you recieve.

= Will BayesianWitch slow down my site? =

No. BayesianWitch loads it's javascript *asynchronously*, which is a fancy way of saying that we don't load our javascript until after your page is fully loaded.

= How reliable is BayesianWitch? =

At BayesianWitch we take reliability very seriously. We take several steps to ensure that your blog always works.

First, if your readers are unable to connect to our servers (perhaps they have a slow connection), your blog post will still work. All that will happen is a *random* call to action will be displayed - it's not *optimal*, but your blog post will not be broken. You can test this out yourself - set up a wordpress blog on your laptop and create a post with a BayesianWitch bandit. Then disconnect from the internet - your blog post will still work and the call to action will still display.

Second, on the server side, we use all the industry standard devops techniques to keep our servers up and running. We have automated monitoring, internal data collection, the works. We also monitor things 24/7.

= What data does BayesianWitch collect? =

BayesianWitch tracks your user's behavior on the site - pageviews, clicks, tweets, likes, etc. We analyze all that data in order to determine which call to action is performing better.

== Changelog ==

0.1    Created the plugin
