import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';

export default class LinkposterSettingsPage extends ExtensionPage {
  content() {
    const tags = app.store.all<Tag>('tags');
    return (
      <div className="container">
        <div className="LinkposterSettingsPage">
          <div className="Form">
            <div className="Form-group">
              <label>Välj tillåtna taggar</label>
                {tags.map((tag) => (
                  <div className="Form-group-checkbox">
                    {this.buildSettingComponent({
                      type: 'boolean',
                      setting: `linkposter.tags.${tag.slug()}`, // Unik nyckel
                      label: tag.name(),
                    })}
                  </div>
                ))}
              <div className="helpText">Välj de taggar som ska omfattas av funktionen.</div>
            </div>
          </div>
          <div className="Form-group">
            {this.submitButton()}
          </div>
        </div>
      </div>
    );
  }
}
