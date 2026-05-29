import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';

export default function() {
  extend(CommentPost.prototype, 'oncreate', function() {

    if (this.attrs.post.number() !== 1) return;
    const discussion = this.attrs.post.discussion();
    console.log(discussion);

    // Hitta Post-body i DOM:en för just denna komponent
    const postBody = this.element.querySelector('.Post-body');

    if (postBody && discussion.attribute('linkposter_url')) {
      const vnode = m(
        'a',
        {
          class: 'linkposterCard',
          href: discussion.attribute('linkposter_url'),
          target: '_blank',
          rel: 'noopener noreferrer'
        },
        m(
          'div',
          {
            class: 'linkposterImage'
          },
          m(
            'figure',
            {},
            m(
              'img',
              {
                src: discussion.attribute('linkposter_thumbnail')
              }
            )
          )
        ),
        m(
          'div',
          {
            class: 'linkposterBody'
          },
          m(
            'span',
            {
              class: 'linkposterTitle'
            },
            discussion.title()
          ),
          m(
            'span',
            {
              class: 'linkposterDescription'
            },
            discussion.attribute('linkposter_description')
          ),
          m(
            'span',
            {},
            discussion.attribute('linkposter_url')
          )
        )
      );
      const renderElement = document.createElement('div');
      const newElement = document.createElement('div');
      m.render(newElement, vnode);
      postBody.prepend(
        newElement
      );
    }
  });
}
