import { extend } from 'flarum/common/extend';
import Model from 'flarum/common/Model';
import Discussion from 'flarum/common/models/Discussion';

// export { default as extend } from './extend';
import extendDiscussion from './extend';

app.initializers.add('redundans/linkposter', () => {
  Discussion.prototype.linkposterUrl = Model.attribute('linkposter_url');
  Discussion.prototype.linkposterDescription = Model.attribute('linkposter_description');
  Discussion.prototype.linkposterThumbnail = Model.attribute('linkposter_thumbnail');
  extendDiscussion();
});
