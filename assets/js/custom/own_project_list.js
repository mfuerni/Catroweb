import $ from 'jquery'
import { Corner, MDCMenu } from '@material/menu'
import { MDCMenuSurfaceFoundation } from '@material/menu-surface'
import Swal from 'sweetalert2'

require('../../styles/components/own_project_list.scss')

export class OwnProjectList {
  constructor (container, apiUrl, theme, emptyMessage = '', actionConfiguration) {
    this.container = container
    this.projectsContainer = container.getElementsByClassName('projects-container')[0]
    this.apiUrl = apiUrl
    this.projectsLoaded = 0
    this.projectsData = {}
    this.projectFetchCount = 99999
    this.empty = false
    this.fetchActive = false
    this.theme = theme
    this.emptyMessage = emptyMessage
    this.projectActionMenu = undefined
    this.actionConfiguration = actionConfiguration
  }

  initialize () {
    this.fetchMore(true)
    this._initActionMenu()
  }

  _initActionMenu () {
    const self = this
    this.projectActionMenu = new MDCMenu(document.getElementById('project-action-menu'))
    this.projectActionMenu.listen('MDCMenu:selected', function (event) {
      if (event.detail.index === 0) { // Set public/private
        self._actionToggleVisibility(self.projectActionMenu.projectId)
      } else if (event.detail.index === 1) { // Delete
        self._actionDeleteProgram(self.projectActionMenu.projectId)
      } else {
        console.error('Invalid menu item selected')
      }
    })
    this.projectActionMenu.setAnchorCorner(Corner.TOP_END)
    this.projectActionMenu.setAbsolutePosition(0, 0)
  }

  fetchMore (clear = false) {
    if (this.empty === true || this.fetchActive === true) {
      return
    }

    this.fetchActive = true
    const self = this

    if (!this.apiUrl.includes('?')) {
      this.apiUrl += '?'
    } else {
      this.apiUrl += '&'
    }

    $.getJSON(this.apiUrl + 'limit=' + this.projectFetchCount + '&offset=' + this.projectsLoaded,
      function (data) {
        if (!Array.isArray(data)) {
          console.error('Data received for own projects is no array!')
          self.container.classList.remove('loading')
          return
        }

        if (clear) {
          Array.prototype.slice.call(self.projectsContainer.childNodes).forEach(function (child) {
            self.projectsContainer.removeChild(child)
          })
        }

        data.forEach(function (project) {
          self.projectsData[project.id] = project
          const projectElement = self._generate(project)
          self.projectsContainer.appendChild(projectElement)
          projectElement.addEventListener('click', function (event) {
            // TODO show (global) loading spinner
          }, false)
        })
        self.container.classList.remove('loading')

        self.projectsLoaded += data.length

        if (self.projectsLoaded === 0 && self.empty === false) {
          self.empty = true
          if (self.emptyMessage) {
            self.projectsContainer.appendChild(document.createTextNode(self.emptyMessage))
            self.container.classList.add('empty-with-text')
          } else {
            self.container.classList.add('empty')
          }
        }

        self.fetchActive = false
      }).fail(function (jqXHR, textStatus, errorThrown) {
      console.error('Failed loading own projects', JSON.stringify(jqXHR), textStatus, errorThrown)
      self.container.classList.remove('loading')
    })
  }

  _generate (data) {
    const self = this
    /*
    * Necessary to support legacy flavoring with URL:
    *   Absolute url always uses new 'app' routing flavor. We have to replace it!
    */
    let projectUrl = data.project_url
    projectUrl = projectUrl.replace('/app/', '/' + this.theme + '/')
    //

    const proj = document.createElement('a')
    proj.className = 'own-project-list__project'
    proj.setAttribute('href', projectUrl)
    proj.dataset.id = data.id

    const img = document.createElement('img')
    img.className = 'lazyload own-project-list__project__image'
    img.dataset.src = data.screenshot_small
    // TODO: generate larger thumbnails and adapt here (change 80w to width of thumbs)
    img.dataset.srcset = data.screenshot_small + ' 80w, ' + data.screenshot_large + ' 480w'
    img.dataset.sizes = '(min-width: 768px) 10vw, 25vw'

    proj.appendChild(img)

    const details = document.createElement('div')
    details.className = 'own-project-list__project__details'
    proj.appendChild(details)

    const name = document.createElement('div')
    name.className = 'own-project-list__project__details__name'
    name.appendChild(document.createTextNode(data.name))
    details.appendChild(name)

    const properties = document.createElement('div')
    properties.className = 'own-project-list__project__details__properties'
    details.appendChild(properties)

    const icons = {
      views: 'visibility',
      download: 'get_app',
      reactions: 'thumb_up',
      author: 'person'
    }

    // eslint-disable-next-line no-array-constructor
    Array('download', 'views', 'reactions', 'comments').forEach(function (propertyKey) {
      if (Object.prototype.hasOwnProperty.call(data, propertyKey)) {
        const propEl = document.createElement('div')
        propEl.className = 'own-project-list__project__details__properties__property'

        const iconEl = document.createElement('span')
        iconEl.className = 'material-icons'
        iconEl.appendChild(document.createTextNode(icons[propertyKey]))
        propEl.appendChild(iconEl)

        const valueEl = document.createElement('span')
        valueEl.className = 'own-project-list__project__details__properties__property__value'
        valueEl.appendChild(document.createTextNode(data[propertyKey]))
        propEl.appendChild(valueEl)

        properties.appendChild(propEl)
      }
    })

    const action = document.createElement('div')
    action.className = 'own-project-list__project__action'
    action.addEventListener('click', function (event) {
      event.preventDefault()
      event.stopPropagation()

      const refreshAndOpenMenu = function () {
        const visibilityItem = self.projectActionMenu.items[0].getElementsByClassName('mdc-list-item__text')[0]
        if (self.projectsData[data.id].private) { // private project
          visibilityItem.innerText = visibilityItem.dataset.textPublic
        } else {
          visibilityItem.innerText = visibilityItem.dataset.textPrivate
        }

        self.projectActionMenu.setAnchorElement(event.target)
        self.projectActionMenu.projectId = data.id
        self.projectActionMenu.open = true
      }

      if (self.projectActionMenu.root.classList.contains(MDCMenuSurfaceFoundation.cssClasses.ANIMATING_CLOSED)) {
        setTimeout(refreshAndOpenMenu, MDCMenuSurfaceFoundation.numbers.TRANSITION_CLOSE_DURATION + 25)
      } else {
        refreshAndOpenMenu()
      }
    }, false)
    proj.appendChild(action)

    const actionIcon = document.createElement('span')
    actionIcon.className = 'material-icons'
    actionIcon.appendChild(document.createTextNode('more_vert'))
    action.appendChild(actionIcon)

    return proj
  }

  _actionDeleteProgram (id) {
    const projectName = this.projectsData[id].name
    const msgParts = this.actionConfiguration.delete.confirmationText
      .replace('%programName%', '“' + projectName + '”').split('\n')
    Swal.fire({
      title: msgParts[0],
      html: msgParts[1] + '<br><br>' + msgParts[2],
      icon: 'warning',
      showCancelButton: true,
      allowOutsideClick: false,
      customClass: {
        confirmButton: 'btn btn-danger',
        cancelButton: 'btn btn-outline-primary'
      },
      buttonsStyling: false,
      confirmButtonText: msgParts[3],
      cancelButtonText: msgParts[4]
    }).then((result) => {
      if (result.value) {
        window.location.href = this.actionConfiguration.delete.url + '/' + id
      }
    })
  }

  _actionToggleVisibility (id) {
    const project = this.projectsData[id]
    const configuration = this.actionConfiguration.visibility
    const msgParts = configuration.confirmationText
      .replaceAll('%programName%', '“' + project.name + '”').split('\n')
    Swal.fire({
      title: msgParts[0],
      html: (project.private) ? msgParts[3] : msgParts[1] + '<br><br>' + msgParts[2],
      icon: 'warning',
      showCancelButton: true,
      allowOutsideClick: false,
      customClass: {
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-outline-primary'
      },
      buttonsStyling: false,
      confirmButtonText: (project.private) ? msgParts[4] : msgParts[5],
      cancelButtonText: msgParts[6]
    }).then((result) => {
      if (result.value) {
        $.get(configuration.url + '/' + id, {}, function (data) {
          if (data === 'true') {
            if (project.private) {
              project.private = false
              // TODO: change visibility lock icon
            } else {
              project.private = true
              // TODO: change visibility lock icon
            }
          } else {
            Swal.fire({
              title: configuration.errorTitle,
              text: configuration.errorMessage,
              icon: 'error',
              customClass: {
                confirmButton: 'btn btn-primary'
              },
              buttonsStyling: false,
              allowOutsideClick: false
            })
          }
        })
      }
    })
  }
}
